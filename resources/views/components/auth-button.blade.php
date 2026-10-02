{{-- Pill submit button for the auth pages. `gradient` on the login sheet,
     `light` (white) on the glass pages that sit directly on the gradient. --}}
@props(['variant' => 'gradient'])

<button
    type="submit"
    {{ $attributes->class([
        'group inline-flex h-11 w-full items-center justify-center gap-2 rounded-full px-6 text-sm font-semibold transition-all duration-300 focus-visible:outline-2 focus-visible:outline-offset-2 active:scale-[0.98]',
        'bg-linear-to-r from-primary-600 via-primary-500 to-secondary-500 bg-size-[200%_100%] bg-left text-white shadow-lg shadow-primary-500/30 hover:bg-right hover:shadow-xl hover:shadow-primary-500/40 focus-visible:outline-primary-500' => $variant === 'gradient',
        'bg-white text-primary-700 shadow-lg shadow-black/20 hover:bg-white/90 hover:shadow-xl focus-visible:outline-white' => $variant === 'light',
    ]) }}
>
    {{ $slot }}
    <flux:icon.arrow-right variant="micro" class="transition-transform duration-300 group-hover:translate-x-1" />
</button>
