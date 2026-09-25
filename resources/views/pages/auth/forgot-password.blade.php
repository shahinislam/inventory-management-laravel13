<x-layouts::auth.focus :title="__('Forgot password')" icon="key" step="1">
    <div class="flex flex-col gap-5 short:gap-4">
        <div class="text-center">
            <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ __('Forgot your password?') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 short:hidden dark:text-zinc-400">{{ __('No worries — we\'ll email you a reset link.') }}</p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="rounded-xl bg-green-50 px-4 py-3 text-center dark:bg-green-500/10" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4 short:gap-3">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                type="email"
                icon="envelope"
                required
                autofocus
                placeholder="email@example.com"
            />

            <x-auth-button data-test="email-password-reset-link-button">{{ __('Email password reset link') }}</x-auth-button>
        </form>

        <a href="{{ route('login') }}" class="group inline-flex items-center justify-center gap-1.5 text-sm font-medium text-zinc-600 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white" wire:navigate>
            <flux:icon.arrow-left variant="micro" class="transition-transform group-hover:-translate-x-0.5" />
            {{ __('Back to log in') }}
        </a>
    </div>
</x-layouts::auth.focus>
