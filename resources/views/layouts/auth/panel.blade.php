{{-- Register: no container — the form floats as glass pills directly on the gradient. --}}
@php
    $companyName = \App\Models\Setting::get('company.name') ?: config('app.name', 'Laravel');
    $companyLogo = \App\Models\Setting::get('company.logo');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased bg-zinc-950">
        <div class="relative flex min-h-svh items-center justify-center overflow-hidden px-4 py-10 short:py-4">
            <div class="auth-gradient absolute inset-0" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-1" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-2" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-3" aria-hidden="true"></div>
            <div class="auth-grid absolute inset-0" aria-hidden="true"></div>

            <div class="auth-rise auth-glass relative z-10 w-full max-w-xl text-white">
                <div class="flex flex-col items-center text-center">
                    <a href="{{ route('home') }}" class="relative" wire:navigate>
                        <span class="absolute inset-0 rounded-full bg-white/30 blur-xl" aria-hidden="true"></span>
                        <span class="relative flex size-14 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/30 backdrop-blur-md short:size-11">
                            @if ($companyLogo)
                                <img src="{{ $companyLogo }}" alt="{{ $companyName }}" class="size-full rounded-full object-cover" />
                            @else
                                <x-app-logo-icon class="size-6 fill-current text-white" />
                                <span class="sr-only">{{ $companyName }}</span>
                            @endif
                        </span>
                    </a>

                    <h1 class="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl short:mt-3 short:text-2xl">
                        {{ __('Get your store') }} <span class="auth-shimmer">{{ __('up and running.') }}</span>
                    </h1>

                    <ul class="mt-5 hidden flex-wrap justify-center gap-2 text-sm sm:flex short:hidden">
                        @foreach ([['cube', __('Products & stock')], ['shopping-cart', __('Point of sale')], ['chart-bar', __('Reports')]] as [$icon, $label])
                            <li class="inline-flex items-center gap-2 rounded-full bg-white/10 py-1.5 pe-4 ps-1.5 ring-1 ring-white/20 backdrop-blur-md">
                                <span class="flex size-7 items-center justify-center rounded-full bg-white/20">
                                    <flux:icon :icon="$icon" variant="micro" class="text-white" />
                                </span>
                                {{ $label }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mt-8 short:mt-5">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
