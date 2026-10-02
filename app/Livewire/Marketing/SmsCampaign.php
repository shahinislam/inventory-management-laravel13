<?php

namespace App\Livewire\Marketing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\SmsService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Send one text to a group of members (offers, win-back, announcements).
 */
class SmsCampaign extends Component
{
    /** Most messages one click may send. */
    public const MAX_PER_SEND = 2000;

    public string $audience = 'all'; // all, spent, recent, lapsed

    public string $minSpend = '5000';

    public string $days = '30';

    public string $message = '';

    /** Invoice statuses that do not count as a purchase (same as MembershipService). */
    private const NOT_COUNTED = ['draft', 'cancelled'];

    private function sales()
    {
        return Invoice::sales()->whereNotIn('invoices.status', self::NOT_COUNTED);
    }

    /** Active members in the chosen audience. */
    protected function recipients(): Builder
    {
        $days = max(1, (int) $this->days);
        $since = now()->subDays($days)->startOfDay();

        $query = Customer::query()
            ->active()
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        return match ($this->audience) {
            'spent' => $query->whereIn('customers.id', $this->sales()
                ->select('customer_id')
                ->whereNotNull('customer_id')
                ->groupBy('customer_id')
                ->havingRaw('SUM(total - returned_amount) >= ?', [max(0, (float) $this->minSpend)])),
            'recent' => $query->whereIn('customers.id', $this->sales()
                ->select('customer_id')
                ->whereNotNull('customer_id')
                ->where('invoice_date', '>=', $since)),
            'lapsed' => $query->whereNotIn('customers.id', $this->sales()
                ->select('customer_id')
                ->whereNotNull('customer_id')
                ->where('invoice_date', '>=', $since)),
            default => $query,
        };
    }

    public function send(SmsService $sms): void
    {
        abort_unless(auth()->user()->hasRole(['admin', 'manager']), 403);

        $this->validate([
            'audience' => 'required|in:all,spent,recent,lapsed',
            'minSpend' => 'required_if:audience,spent|nullable|numeric|min:0',
            'days' => 'required_if:audience,recent,lapsed|nullable|integer|min:1|max:3650',
            'message' => 'required|string|max:1000',
        ], [
            'message.required' => 'Write the message first.',
        ]);

        $total = $this->recipients()->count();
        if ($total === 0) {
            $this->addError('audience', 'No members match this audience.');

            return;
        }

        $shop = Setting::get('company.name', config('app.name'));
        $queued = 0;

        $this->recipients()
            ->orderBy('customers.id')
            ->limit(self::MAX_PER_SEND)
            ->get()
            ->each(function (Customer $customer) use ($sms, $shop, &$queued) {
                $sms->send($customer->phone, $sms->render($this->message, [
                    'name' => $customer->name,
                    'shop' => $shop,
                ]), $customer, 'campaign');
                $queued++;
            });

        $note = $total > self::MAX_PER_SEND
            ? ' Only the first '.number_format(self::MAX_PER_SEND).' of '.number_format($total).' were sent — send again later with a narrower audience for the rest.'
            : '';

        session()->flash('success', "Queued {$queued} messages.".$note);
        $this->message = '';
    }

    public function render(SmsService $sms)
    {
        $count = $this->recipients()->count();
        $sample = $this->recipients()->orderBy('customers.name')->limit(5)->pluck('name');

        // Preview filled in for the first sample member; lengths vary a little per name.
        $preview = $sms->render($this->message, [
            'name' => $sample->first() ?? 'Customer',
            'shop' => Setting::get('company.name', config('app.name')),
        ]);
        $segments = SmsService::segments($preview);

        return view('livewire.marketing.sms-campaign', [
            'count' => $count,
            'sample' => $sample,
            'preview' => $preview,
            'length' => mb_strlen($preview),
            'segments' => $segments,
            'totalSms' => min($count, self::MAX_PER_SEND) * $segments,
            'configured' => $sms->isConfigured(),
            'unicode' => preg_match('/[^\x00-\x7F]/', $preview) === 1,
        ])->layout('layouts.app', ['title' => 'SMS to Members']);
    }
}
