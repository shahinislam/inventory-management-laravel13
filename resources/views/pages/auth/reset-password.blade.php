<x-layouts::auth.focus :title="__('Reset password')" icon="lock-closed" step="2">
    <div class="flex flex-col gap-5 short:gap-4">
        <div class="text-center">
            <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ __('Set a new password') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 short:hidden dark:text-zinc-400">{{ __('Choose something strong you haven\'t used before.') }}</p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="rounded-xl bg-green-50 px-4 py-3 text-center dark:bg-green-500/10" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4 short:gap-3">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- Email Address -->
            <flux:input
                name="email"
                value="{{ request('email') }}"
                :label="__('Email')"
                type="email"
                icon="envelope"
                required
                autocomplete="email"
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
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <x-auth-button data-test="reset-password-button">{{ __('Reset password') }}</x-auth-button>
        </form>
    </div>
</x-layouts::auth.focus>
