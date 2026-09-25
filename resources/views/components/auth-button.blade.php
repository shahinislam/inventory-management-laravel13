{{-- Gradient submit button used by the auth pages (layouts/auth/gradient). --}}
<button
    type="submit"
    {{ $attributes->class('group relative mt-1 inline-flex h-11 w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-linear-to-r from-primary-600 via-primary-500 to-secondary-500 bg-size-[200%_100%] bg-left px-4 text-sm font-semibold text-white shadow-lg shadow-primary-500/30 transition-all duration-500 hover:bg-right hover:shadow-xl hover:shadow-primary-500/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 active:scale-[0.99]') }}
>
    {{ $slot }}
    <flux:icon.arrow-right variant="micro" class="transition-transform duration-300 group-hover:translate-x-1" />
</button>
