<?php

use App\Livewire\Marketing\SmsCampaign;
use App\Livewire\Marketing\SmsLog;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SmsService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
    Http::fake();
});

function smsSale(Customer $customer, float $total, $date = null, string $status = 'paid'): Invoice
{
    return Invoice::create([
        'customer_id' => $customer->id,
        'created_by' => auth()->id(),
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'status' => $status,
        'subtotal' => $total,
        'total' => $total,
        'paid_amount' => $total,
        'invoice_date' => $date ?? now(),
    ]);
}

it('only logs messages when SMS is not set up', function () {
    Customer::factory()->count(3)->create();
    Customer::factory()->inactive()->create();

    Livewire::test(SmsCampaign::class)
        ->assertSee('SMS gateway not set up')
        ->assertViewHas('count', 3)
        ->set('message', 'Hi {name}, sale at {shop}!')
        ->call('send')
        ->assertHasNoErrors();

    expect(ActivityLog::where('action', 'sms_logged')->count())->toBe(3)
        ->and(ActivityLog::where('action', 'sms_logged')->first()->new_values['type'])->toBe('campaign')
        ->and(ActivityLog::where('action', 'sms_logged')->first()->new_values['message'])->not->toContain('{name}');

    Http::assertNothingSent();
});

it('posts to the gateway and marks the log sent when SMS is set up', function () {
    Setting::set('sms.enabled', true);
    Setting::set('sms.gateway_url', 'https://sms.test/send');
    $customer = Customer::factory()->create();

    $log = app(SmsService::class)->send($customer->phone, 'Hello', $customer, 'campaign', now: true);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://sms.test/send'));
    expect($log->fresh()->action)->toBe('sms_sent');
});

it('counts members who spent at least an amount, net of returns', function () {
    $big = Customer::factory()->create();
    $small = Customer::factory()->create();
    $returned = Customer::factory()->create();
    $drafted = Customer::factory()->create();
    Customer::factory()->create(); // never bought

    smsSale($big, 3000);
    smsSale($big, 3000);
    smsSale($small, 1000);
    smsSale($returned, 6000)->update(['returned_amount' => 2000]);
    smsSale($drafted, 9000, null, 'draft');

    Livewire::test(SmsCampaign::class)
        ->set('audience', 'spent')
        ->set('minSpend', '5000')
        ->assertViewHas('count', 1)
        ->assertViewHas('sample', fn ($s) => $s->all() === [$big->name]);
});

it('finds recent buyers and lapsed members', function () {
    $recent = Customer::factory()->create();
    $lapsed = Customer::factory()->create();
    Customer::factory()->create(); // never bought

    smsSale($recent, 100, now()->subDays(3));
    smsSale($lapsed, 100, now()->subDays(90));

    Livewire::test(SmsCampaign::class)
        ->set('days', '30')
        ->set('audience', 'recent')
        ->assertViewHas('count', 1)
        ->set('audience', 'lapsed')
        ->assertViewHas('count', 2)
        ->assertViewHas('sample', fn ($s) => ! $s->contains($recent->name));
});

it('shows the total number of SMS parts', function () {
    Customer::factory()->count(2)->create();

    Livewire::test(SmsCampaign::class)
        ->set('message', str_repeat('a', 200))
        ->assertViewHas('segments', 2)
        ->assertViewHas('totalSms', 4);
});

it('lists logged messages in the SMS log with filters', function () {
    $customer = Customer::factory()->create(['phone' => '01711111111']);
    $other = Customer::factory()->create(['phone' => '01822222222']);
    app(SmsService::class)->send($customer->phone, 'First message', $customer, 'campaign');
    app(SmsService::class)->send($other->phone, 'Second message', $other, 'receipt');

    Livewire::test(SmsLog::class)
        ->assertSee('01711111111')
        ->assertSee('01822222222')
        ->assertSee('Logged only')
        ->set('type', 'receipt')
        ->assertDontSee('01711111111')
        ->assertSee('01822222222')
        ->set('type', '')
        ->set('search', '0171')
        ->assertSee('01711111111')
        ->assertDontSee('01822222222')
        ->set('search', '')
        ->set('status', 'sent')
        ->assertSee('No messages match');
});
