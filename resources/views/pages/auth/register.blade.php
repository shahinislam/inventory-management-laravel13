<x-layouts::auth.panel :title="__('Register')">
    <!-- Session Status -->
    <x-auth-session-status class="mb-4 rounded-full bg-emerald-400/15 px-4 py-2 text-center text-emerald-100! ring-1 ring-emerald-300/30" :status="session('status')" />

    <form method="POST" action="{{ route('register.store') }}" class="grid gap-4 sm:grid-cols-2">
        @csrf

        <!-- Name -->
        <flux:input
            name="name"
            :label="__('Name')"
            :value="old('name')"
            type="text"
            icon="user"
            required
            autofocus
            autocomplete="name"
            :placeholder="__('Full name')"
        />

        <!-- Email Address -->
        <flux:input
            name="email"
            :label="__('Email address')"
            :value="old('email')"
            type="email"
            icon="envelope"
            required
            autocomplete="email"
            placeholder="email@example.com"
        />

        <!-- Password -->
        <flux:input
            name="password"
            :label="__('Password')"
            type="password"
            icon="lock-closed"
            required
            autocomplete="new-password"
            :placeholder="__('Password')"
            passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            viewable
        />

        <!-- Confirm Password -->
        <flux:input
            name="password_confirmation"
            :label="__('Confirm password')"
            type="password"
            icon="lock-closed"
            required
            autocomplete="new-password"
            :placeholder="__('Repeat password')"
            passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            viewable
        />

        <x-auth-button variant="light" class="mt-2 sm:col-span-2" data-test="register-user-button">{{ __('Create account') }}</x-auth-button>
    </form>

    <p class="mt-6 text-center text-sm text-white/70 short:mt-4">
        {{ __('Already have an account?') }}
        <a href="{{ route('login') }}" class="font-semibold text-white underline decoration-white/40 underline-offset-4 transition hover:decoration-white" wire:navigate>{{ __('Log in') }}</a>
    </p>
</x-layouts::auth.panel>
