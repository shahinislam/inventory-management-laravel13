<x-layouts::auth.focus :title="__('Forgot password')" icon="key" step="1">
    <div class="flex flex-col gap-5 text-center short:gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight short:text-2xl">{{ __('Forgot your password?') }}</h1>
            <p class="mt-2 text-sm text-white/70 short:hidden">{{ __('No worries — we\'ll email you a reset link.') }}</p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="rounded-full bg-emerald-400/15 px-4 py-2 text-emerald-100! ring-1 ring-emerald-300/30" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4 text-start">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                icon="envelope"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <x-auth-button variant="light" class="mt-1" data-test="email-password-reset-link-button">{{ __('Email password reset link') }}</x-auth-button>
        </form>

        <a href="{{ route('login') }}" class="group inline-flex items-center justify-center gap-1.5 text-sm font-medium text-white/70 transition hover:text-white" wire:navigate>
            <flux:icon.arrow-left variant="micro" class="transition-transform group-hover:-translate-x-0.5" />
            {{ __('Back to log in') }}
        </a>
    </div>
</x-layouts::auth.focus>
