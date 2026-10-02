{{-- Forgot / reset password: a glowing orb badge with glass pill fields on the gradient. --}}
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
        <div class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden px-4 py-10 short:py-4">
            <div class="auth-gradient absolute inset-0" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-1" aria-hidden="true"></div>
            <div class="auth-blob auth-blob-2" aria-hidden="true"></div>
            <div class="auth-grid absolute inset-0" aria-hidden="true"></div>

            <div class="auth-rise auth-glass relative z-10 flex w-full max-w-sm flex-col items-center text-white">
                {{-- Orb badge --}}
                <div class="relative">
                    <span class="absolute -inset-4 animate-ping rounded-full bg-white/15 [animation-duration:2.8s]" aria-hidden="true"></span>
                    <span class="absolute -inset-2 rounded-full bg-white/20 blur-lg" aria-hidden="true"></span>
                    <span class="relative flex size-20 items-center justify-center rounded-full bg-linear-to-br from-white/40 to-white/10 ring-1 ring-white/40 backdrop-blur-md short:size-14">
                        <flux:icon :icon="$icon" class="size-9 text-white short:size-7" />
                    </span>
                </div>

                {{-- Steps as connected dots --}}
                <ol class="mt-8 flex items-center gap-2 text-xs font-medium text-white/60 short:mt-4">
                    @foreach ([1 => __('Request link'), 2 => __('New password')] as $n => $label)
                        @if ($n > 1)
                            <li class="h-px w-8 {{ $step >= $n ? 'bg-white/80' : 'bg-white/30' }}" aria-hidden="true"></li>
                        @endif
                        <li class="flex items-center gap-2 {{ $step >= $n ? 'text-white' : '' }}" @if ($step === $n) aria-current="step" @endif>
                            <span class="size-2.5 rounded-full {{ $step >= $n ? 'bg-white shadow-[0_0_10px_rgb(255_255_255/0.9)]' : 'ring-1 ring-white/50' }}"></span>
                            {{ $label }}
                        </li>
                    @endforeach
                </ol>

                <div class="mt-6 w-full short:mt-4">
                    {{ $slot }}
                </div>

                <a href="{{ route('home') }}" class="mt-8 text-sm font-medium text-white/60 transition hover:text-white [@media(max-height:760px)]:hidden" wire:navigate>
                    {{ $companyName }}
                </a>
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
