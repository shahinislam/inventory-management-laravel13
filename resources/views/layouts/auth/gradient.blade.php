{{-- Login: gradient showcase on the left, form on a curved sheet on the right. --}}
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
        <div class="relative flex min-h-svh flex-col overflow-hidden lg:h-svh lg:flex-row">
            <div class="auth-gradient absolute inset-0" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-1" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-2" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-3" aria-hidden="true"></div>
            <div class="auth-grid absolute inset-0" aria-hidden="true"></div>

            {{-- Showcase --}}
            <aside class="relative z-10 flex flex-col justify-between gap-8 p-6 text-white sm:p-10 lg:min-h-0 lg:flex-1 lg:overflow-hidden xl:px-16 [@media(min-height:860px)]:lg:py-16">
                <a href="{{ route('home') }}" class="flex items-center gap-3" wire:navigate>
                    <span class="flex size-11 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25 backdrop-blur-md">
                        @if ($companyLogo)
                            <img src="{{ $companyLogo }}" alt="" class="size-11 rounded-full object-cover" />
                        @else
                            <x-app-logo-icon class="size-5 fill-current text-white" />
                        @endif
                    </span>
                    <span class="hidden text-lg font-semibold tracking-tight lg:inline">{{ $companyName }}</span>
                </a>

                <div class="relative hidden lg:block">
                    <div class="max-w-md">
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium ring-1 ring-white/20 backdrop-blur-md">
                            <span class="relative flex size-2">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-300 opacity-75"></span>
                                <span class="relative inline-flex size-2 rounded-full bg-emerald-300"></span>
                            </span>
                            {{ __('Inventory, sales & purchases in one place') }}
                        </span>

                        <h1 class="mt-6 text-4xl font-semibold leading-[1.05] tracking-tight xl:text-5xl [@media(min-height:860px)]:xl:text-6xl">
                            {{ __('Every item,') }}<br>
                            <span class="auth-shimmer">{{ __('always in sight.') }}</span>
                        </h1>

                        <p class="mt-5 text-lg text-white/75">
                            {{ __('Track stock across locations, ring up sales at the counter and keep every invoice and payment reconciled — in real time.') }}
                        </p>
                    </div>

                    {{-- Stock ring with orbiting module bubbles --}}
                    <div class="absolute right-0 top-1/2 hidden size-56 -translate-y-1/2 xl:block" aria-hidden="true">
                        <div class="absolute inset-0 rounded-full border border-dashed border-white/30"></div>

                        <div class="absolute inset-10 flex flex-col items-center justify-center rounded-full bg-white/10 backdrop-blur-md">
                            <div class="auth-ring absolute inset-0 rounded-full"></div>
                            <span class="text-3xl font-semibold">82%</span>
                            <span class="text-xs text-white/70">{{ __('in stock') }}</span>
                        </div>

                        <div class="auth-orbit absolute inset-0">
                            @foreach ([['cube', 'left-1/2 top-0'], ['shopping-cart', 'left-full top-1/2'], ['chart-bar', 'left-1/2 top-full'], ['truck', 'left-0 top-1/2']] as [$icon, $position])
                                <div class="absolute {{ $position }} -ml-6 -mt-6">
                                    <span class="auth-bubble flex size-12 items-center justify-center rounded-full bg-white/20 shadow-lg shadow-black/10 ring-1 ring-white/30 backdrop-blur-md">
                                        <flux:icon :icon="$icon" variant="mini" class="size-5 text-white" />
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <p class="hidden text-sm text-white/55 lg:block">&copy; {{ date('Y') }} {{ $companyName }}</p>
            </aside>

            {{-- Form on a curved sheet: an S-curve on desktop, a rounded top edge on mobile. --}}
            <main class="relative z-10 flex flex-1 items-center justify-center rounded-t-[2.5rem] bg-white px-6 py-10 sm:px-12 lg:w-[480px] lg:flex-none lg:rounded-none lg:py-6 short:py-4 dark:bg-zinc-900">
                <svg class="absolute inset-y-0 right-full hidden h-full w-24 fill-white lg:block dark:fill-zinc-900" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M100 0 H70 C 10 20, 100 45, 45 62 C 5 75, 40 92, 62 100 H100 Z" />
                </svg>

                <div class="auth-rise auth-pill relative w-full max-w-sm">
                    {{ $slot }}
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
