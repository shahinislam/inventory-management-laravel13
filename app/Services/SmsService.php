<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Text messages through a generic HTTP SMS gateway (most Bangladeshi gateways
 * take a URL with number / message / api key / sender parameters; the
 * parameter names are configurable in Settings → SMS).
 *
 * Every message is recorded in activity_logs (action sms_logged, sms_sent or
 * sms_failed). Until SMS is enabled and a gateway URL is set, messages are only
 * logged — nothing is sent.
 */
class SmsService
{
    public function isConfigured(): bool
    {
        return Setting::bool('sms.enabled') && filled(Setting::get('sms.gateway_url'));
    }

    /**
     * Queue a message. The HTTP call runs after the response is sent, so a slow
     * gateway never holds up the till. Pass $now to send inline (tests, the
     * "send test SMS" button).
     */
    public function send(string $phone, string $message, Model $about, string $type, bool $now = false): ActivityLog
    {
        $log = ActivityLog::log(
            'sms_logged',
            $about,
            [],
            ['phone' => $phone, 'message' => $message, 'type' => $type, 'status' => 'logged'],
            "SMS ({$type}) to {$phone}",
        );

        if ($this->isConfigured()) {
            $now ? $this->deliver($log) : defer(fn () => $this->deliver($log));
        }

        return $log;
    }

    /** Make the gateway call and record the outcome on the log row. */
    public function deliver(ActivityLog $log): void
    {
        $values = $log->new_values;

        $params = array_filter([
            Setting::get('sms.param_to', 'number') => $values['phone'],
            Setting::get('sms.param_message', 'message') => $values['message'],
            Setting::get('sms.param_key', 'api_key') => Setting::get('sms.api_key'),
            Setting::get('sms.param_sender', 'senderid') => Setting::get('sms.sender_id'),
        ], fn ($v, $k) => filled($k) && $v !== null, ARRAY_FILTER_USE_BOTH);

        try {
            $request = Http::timeout(10)->acceptJson();
            $url = (string) Setting::get('sms.gateway_url');

            $response = strtoupper((string) Setting::get('sms.http_method', 'POST')) === 'GET'
                ? $request->get($url, $params)
                : $request->asForm()->post($url, $params);

            $ok = $response->successful();
            $body = mb_substr($response->body(), 0, 500);
        } catch (Throwable $e) {
            $ok = false;
            $body = mb_substr($e->getMessage(), 0, 500);
        }

        $log->update([
            'action' => $ok ? 'sms_sent' : 'sms_failed',
            'new_values' => array_merge($values, ['status' => $ok ? 'sent' : 'failed', 'response' => $body]),
        ]);
    }

    /** Fill {placeholders} in a template. */
    public function render(string $template, array $data): string
    {
        return strtr($template, collect($data)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
    }

    /** Thank-you text after a member's sale, when switched on. */
    public function saleReceipt(Invoice $invoice): void
    {
        if (! Setting::bool('sms.send_receipt') || ! $invoice->customer_id || blank($invoice->customer_phone)) {
            return;
        }

        $customer = Customer::find($invoice->customer_id);
        if (! $customer) {
            return;
        }

        $this->send($invoice->customer_phone, $this->render(
            (string) Setting::get('sms.receipt_template', 'Thank you {name}! Bill {invoice}: {total}. - {shop}'),
            [
                'name' => $customer->name,
                'invoice' => $invoice->invoice_number,
                'total' => money($invoice->total),
                'spent' => money(app(MembershipService::class)->customerSpend($customer->id)),
                'shop' => Setting::get('company.name', config('app.name')),
            ],
        ), $customer, 'receipt');
    }

    /** Tell a member about the rewards given on this sale (same switch as receipts). */
    public function rewardsGiven(Invoice $invoice): void
    {
        if (! Setting::bool('sms.send_receipt') || ! $invoice->customer_id || blank($invoice->customer_phone)) {
            return;
        }

        $redemptions = $invoice->rewardRedemptions()->with('giftProduct')->get();
        $customer = Customer::find($invoice->customer_id);
        if ($redemptions->isEmpty() || ! $customer) {
            return;
        }

        $this->send($invoice->customer_phone, $this->render(
            (string) Setting::get('sms.reward_template', 'Congrats {name}! You received {reward} on bill {invoice}. - {shop}'),
            [
                'name' => $customer->name,
                'reward' => $redemptions->map->description->implode(', '),
                'invoice' => $invoice->invoice_number,
                'shop' => Setting::get('company.name', config('app.name')),
            ],
        ), $customer, 'reward');
    }

    /** SMS length in segments: 160 chars (GSM) or 70 (Unicode/Bangla) per part. */
    public static function segments(string $message): int
    {
        $length = mb_strlen($message);
        if ($length === 0) {
            return 0;
        }

        $unicode = preg_match('/[^\x00-\x7F]/', $message) === 1;
        $single = $unicode ? 70 : 160;
        $multi = $unicode ? 67 : 153;

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }
}
