<?php

use App\Models\User;

it('shows the permissions matrix with full and view-only access', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

    $this->get(route('settings.roles'))
        ->assertOk()
        ->assertSee('Full access')
        ->assertSee('View only')
        ->assertSee('Payment Accounts')
        ->assertSee('Member Rewards');
});

it('keeps the roles page admin-only', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));

    $this->get(route('settings.roles'))->assertForbidden();
})->with(['manager', 'staff', 'viewer']);
