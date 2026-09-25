<x-layouts::auth.panel :title="__('Register')">
    <div class="flex flex-col gap-6 short:gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ __('Create your account') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('It only takes a minute to get started.') }}</p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="rounded-xl bg-green-50 px-4 py-3 text-center dark:bg-green-500/10" :status="session('status')" />

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

            <x-auth-button class="sm:col-span-2" data-test="register-user-button">{{ __('Create account') }}</x-auth-button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth.panel>
