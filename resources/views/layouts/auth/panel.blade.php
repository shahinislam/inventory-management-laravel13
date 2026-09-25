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
        <div class="relative flex min-h-svh items-center justify-center overflow-hidden p-4 sm:p-8 short:py-3">
            <div class="auth-gradient absolute inset-0" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-1" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-2" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-3" aria-hidden="true"></div>
            <div class="auth-grid absolute inset-0" aria-hidden="true"></div>

            {{-- One wide card: brand panel on the left, form on the right. --}}
            <div class="auth-card relative z-10 grid w-full max-w-4xl overflow-hidden rounded-3xl bg-white shadow-2xl shadow-black/40 ring-1 ring-black/5 md:grid-cols-5 dark:bg-zinc-900/90 dark:ring-white/10 dark:backdrop-blur-xl">
                <aside class="relative hidden flex-col justify-between overflow-hidden bg-linear-to-br from-primary-600 via-primary-700 to-secondary-700 p-8 text-white md:col-span-2 md:flex short:p-6">
                    <div class="pointer-events-none absolute -right-16 -top-16 size-56 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
                    <div class="pointer-events-none absolute -bottom-20 -left-10 size-64 rounded-full bg-tertiary-400/25 blur-3xl" aria-hidden="true"></div>

                    <a href="{{ route('home') }}" class="relative flex items-center gap-3" wire:navigate>
                        @if ($companyLogo)
                            <img src="{{ $companyLogo }}" alt="" class="size-10 rounded-xl object-cover ring-1 ring-white/30" />
                        @else
                            <span class="flex size-10 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25">
                                <x-app-logo-icon class="size-5 fill-current text-white" />
                            </span>
                        @endif
                        <span class="text-lg font-semibold tracking-tight">{{ $companyName }}</span>
                    </a>

                    <div class="relative my-10 short:my-6">
                        <h2 class="text-3xl font-semibold leading-tight tracking-tight">
                            {{ __('Get your store') }}<br>
                            <span class="auth-shimmer">{{ __('up and running.') }}</span>
                        </h2>

                        <ul class="mt-6 space-y-4 text-sm short:mt-4 short:space-y-3">
                            @foreach ([
                                ['cube', __('Products & stock'), __('Track every item across locations')],
                                ['shopping-cart', __('Point of sale'), __('Ring up sales in seconds')],
                                ['chart-bar', __('Reports'), __('Sales, dues and profit at a glance')],
                            ] as [$icon, $label, $hint])
                                <li class="flex items-start gap-3">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/15 ring-1 ring-white/20">
                                        <flux:icon :icon="$icon" variant="mini" class="size-4 text-white" />
                                    </span>
                                    <span>
                                        <span class="block font-medium">{{ $label }}</span>
                                        <span class="block text-white/70">{{ $hint }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <p class="relative text-xs text-white/60">&copy; {{ date('Y') }} {{ $companyName }}</p>
                </aside>

                <main class="p-6 sm:p-8 md:col-span-3 md:p-10 short:md:p-6">
                    <a href="{{ route('home') }}" class="mb-6 flex items-center justify-center gap-2 md:hidden" wire:navigate>
                        <span class="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-primary-500 to-secondary-500 shadow-lg shadow-primary-500/30">
                            <x-app-logo-icon class="size-5 fill-current text-white" />
                        </span>
                        <span class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $companyName }}</span>
                    </a>

                    {{ $slot }}
                </main>
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
