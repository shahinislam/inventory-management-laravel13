@php
    $companyName = \App\Models\Setting::get('company.name') ?: config('app.name', 'Laravel');
    $icon ??= 'key';
    $step = (int) ($step ?? 1);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased bg-zinc-950">
        <div class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden p-4 sm:p-8 short:py-3">
            <div class="auth-gradient absolute inset-0" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-1" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-2" aria-hidden="true"></div>
            <div class="auth-grid absolute inset-0" aria-hidden="true"></div>

            {{-- Password recovery steps --}}
            <ol class="relative z-10 mb-10 flex items-center gap-3 text-xs font-medium text-white/60 short:mb-9">
                @foreach ([1 => __('Request link'), 2 => __('New password')] as $n => $label)
                    @if ($n > 1)
                        <li class="h-px w-10 {{ $step >= $n ? 'bg-white/80' : 'bg-white/30' }}" aria-hidden="true"></li>
                    @endif
                    <li class="flex items-center gap-2 {{ $step >= $n ? 'text-white' : '' }}" @if ($step === $n) aria-current="step" @endif>
                        <span class="flex size-6 items-center justify-center rounded-full ring-1 {{ $step >= $n ? 'bg-white text-primary-700 ring-white' : 'ring-white/40' }}">
                            @if ($step > $n)
                                <flux:icon.check variant="micro" />
                            @else
                                {{ $n }}
                            @endif
                        </span>
                        {{ $label }}
                    </li>
                @endforeach
            </ol>

            <div class="auth-card relative z-10 w-full max-w-sm rounded-2xl bg-white px-6 pb-6 pt-12 short:pb-5 short:pt-10 shadow-2xl shadow-black/40 ring-1 ring-black/5 sm:px-7 dark:bg-zinc-900/90 dark:ring-white/10 dark:backdrop-blur-xl">
                {{-- Icon badge sitting on the card's top edge --}}
                <div class="absolute -top-8 left-1/2 -translate-x-1/2">
                    <span class="absolute inset-0 animate-ping rounded-2xl bg-primary-400/30 [animation-duration:2.5s]" aria-hidden="true"></span>
                    <span class="relative flex size-16 items-center justify-center rounded-2xl bg-linear-to-br from-primary-500 via-primary-600 to-secondary-500 shadow-xl shadow-primary-600/40 ring-4 ring-white dark:ring-zinc-900">
                        <flux:icon :icon="$icon" class="size-7 text-white" />
                    </span>
                </div>

                {{ $slot }}
            </div>

            <a href="{{ route('home') }}" class="relative z-10 mt-6 text-sm font-medium text-white/70 transition hover:text-white short:hidden" wire:navigate>
                {{ $companyName }}
            </a>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
