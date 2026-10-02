<x-layouts::auth.focus :title="__('Reset password')" icon="lock-closed" step="2">
    <div class="flex flex-col gap-5 text-center short:gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight short:text-2xl">{{ __('Set a new password') }}</h1>
            <p class="mt-2 text-sm text-white/70 [@media(max-height:760px)]:hidden">{{ __('Choose something strong you haven\'t used before.') }}</p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="rounded-full bg-emerald-400/15 px-4 py-2 text-emerald-100! ring-1 ring-emerald-300/30" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4 text-start short:gap-3">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- Email Address -->
            <flux:input
                name="email"
                :value="old('email', request('email'))"
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

            <x-auth-button variant="light" class="mt-1" data-test="reset-password-button">{{ __('Reset password') }}</x-auth-button>
        </form>
    </div>
</x-layouts::auth.focus>
