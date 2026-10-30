<?php

use App\Models\User;

// Public sign-up is disabled; admins create accounts under Settings → Users.

test('registration screen is not available', function () {
    $this->get('/register')->assertNotFound();
});

test('registration requests are rejected', function () {
    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('login page has no sign up link', function () {
    $this->get(route('login'))->assertOk()->assertDontSee('Sign up');
});
