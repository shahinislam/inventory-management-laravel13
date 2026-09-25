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
        <div class="relative flex min-h-svh overflow-hidden lg:h-svh">
            {{-- Full-bleed brand gradient. Built from the theme ramps so it follows
                 Settings → Theme rather than a hard-coded palette. --}}
            <div class="auth-gradient absolute inset-0" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-1" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-2" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-3" aria-hidden="true"></div>
            <div class="auth-grid absolute inset-0" aria-hidden="true"></div>

            {{-- Showcase panel (large screens only) --}}
            <aside class="relative z-10 hidden min-h-0 flex-1 flex-col justify-between gap-8 overflow-hidden p-10 text-white lg:flex xl:px-16 [@media(min-height:860px)]:py-16">
                <a href="{{ route('home') }}" class="flex items-center gap-3" wire:navigate>
                    <span class="flex size-11 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25 backdrop-blur-md">
                        <x-app-logo-icon class="size-6 fill-current text-white" />
                    </span>
                    <span class="text-lg font-semibold tracking-tight">{{ $companyName }}</span>
                </a>

                <div class="max-w-lg">
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

                    {{-- Floating preview cards --}}
                    <div class="relative mt-10 h-48 [@media(max-height:760px)]:hidden">
                        <div class="auth-float absolute left-0 top-0 w-64 rounded-2xl bg-white/10 p-4 ring-1 ring-white/20 backdrop-blur-xl shadow-2xl shadow-black/20">
                            <div class="flex items-center justify-between text-xs text-white/70">
                                <span>{{ __('Weekly sales') }}</span>
                                <span class="rounded-full bg-emerald-400/20 px-2 py-0.5 font-medium text-emerald-200">▲ {{ __('trending') }}</span>
                            </div>
                            <div class="mt-4 flex h-16 items-end gap-1.5">
                                @foreach ([35, 55, 40, 70, 60, 85, 100] as $h)
                                    <span class="auth-bar flex-1 rounded-t-md bg-linear-to-t from-white/40 to-white/90" style="height: {{ $h }}%; animation-delay: {{ $loop->index * 80 }}ms"></span>
                                @endforeach
                            </div>
                        </div>

                        <div class="auth-float auth-float-delay absolute left-56 top-20 w-60 rounded-2xl bg-white/10 p-4 ring-1 ring-white/20 backdrop-blur-xl shadow-2xl shadow-black/20">
                            <div class="flex items-center gap-3">
                                <span class="flex size-9 items-center justify-center rounded-xl bg-white/20">
                                    <flux:icon.cube class="size-5 text-white" />
                                </span>
                                <div>
                                    <p class="text-sm font-medium">{{ __('Stock in sync') }}</p>
                                    <p class="text-xs text-white/65">{{ __('Every warehouse, live') }}</p>
                                </div>
                            </div>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/15">
                                <div class="auth-progress h-full rounded-full bg-white/80"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-sm text-white/55">&copy; {{ date('Y') }} {{ $companyName }}</p>
            </aside>

            {{-- Form panel --}}
            <main class="relative z-10 flex w-full items-center justify-center p-4 sm:p-8 lg:w-[460px] lg:shrink-0 lg:overflow-y-auto lg:px-10 lg:py-6 short:py-3">
                <div class="auth-card w-full max-w-sm rounded-2xl bg-white p-6 short:p-5 shadow-2xl shadow-black/30 ring-1 ring-black/5 sm:p-7 dark:bg-zinc-900/90 dark:ring-white/10 dark:backdrop-blur-xl">
                    <a href="{{ route('home') }}" class="mb-6 flex flex-col items-center gap-3 text-center short:mb-5 short:flex-row short:justify-center" wire:navigate>
                        @if ($companyLogo)
                            <img src="{{ $companyLogo }}" alt="" class="size-12 short:size-9 rounded-xl object-cover shadow-lg shadow-primary-500/20" />
                        @else
                            <span class="flex size-12 short:size-9 items-center justify-center rounded-xl bg-linear-to-br from-primary-500 to-secondary-500 shadow-lg shadow-primary-500/30">
                                <x-app-logo-icon class="size-6 fill-current text-white" />
                            </span>
                        @endif
                        <span class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $companyName }}</span>
                    </a>

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
