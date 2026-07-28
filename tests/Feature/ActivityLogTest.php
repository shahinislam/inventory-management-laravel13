<?php

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->actingAs($this->user);
});

it('logs when a model is created', function () {
    $product = Product::factory()->create(['name' => 'Blue Widget']);

    $log = ActivityLog::forModel(Product::class, $product->id)->where('action', 'created')->sole();

    expect($log->user_id)->toBe($this->user->id)
        ->and($log->description)->toBe('Product created: Blue Widget')
        ->and($log->new_values)->toHaveKey('name');
});

it('logs old and new values when a model is updated', function () {
    $product = Product::factory()->create(['selling_price' => 200]);

    $product->update(['selling_price' => 250]);

    $log = ActivityLog::forModel(Product::class, $product->id)->where('action', 'updated')->sole();

    expect($log->old_values['selling_price'])->toEqual(200)
        ->and($log->new_values['selling_price'])->toEqual(250);
});

it('logs when a model is deleted', function () {
    $product = Product::factory()->create(['name' => 'Doomed']);

    $product->delete();

    $log = ActivityLog::forModel(Product::class, $product->id)->where('action', 'deleted')->sole();

    expect($log->description)->toBe('Product deleted: Doomed')
        ->and($log->old_values)->toHaveKey('name');
});

it('does not log a save that changed nothing', function () {
    $product = Product::factory()->create();

    $before = ActivityLog::where('action', 'updated')->count();
    $product->touch();

    expect(ActivityLog::where('action', 'updated')->count())->toBe($before);
});

it('never stores password hashes in the audit trail', function () {
    $user = User::factory()->create();

    $log = ActivityLog::forModel(User::class, $user->id)->where('action', 'created')->sole();

    expect($log->new_values)->not->toHaveKey('password')
        ->and($log->new_values)->not->toHaveKey('remember_token')
        ->and($log->new_values)->toHaveKey('email');
});

it('does not audit the activity log itself', function () {
    Product::factory()->create();

    expect(ActivityLog::where('model_type', ActivityLog::class)->count())->toBe(0);
});

it('records the acting user on each entry', function () {
    $product = Product::factory()->create();

    expect(ActivityLog::forModel(Product::class, $product->id)->sole()->user_id)
        ->toBe($this->user->id);
});
