{{--
    Shared search box with a keyboard-navigable results dropdown.

    Every search-and-pick field in the app goes through this so they all look
    and behave the same: arrow keys move the highlight, Enter picks it, Esc
    closes the list. Once something is picked (pass `selected`), the input is
    swapped for a chip that carries its own clear button.

    The result count is read from the DOM rather than baked in from Blade, so
    the highlight survives Livewire re-rendering the list while the user types.

    Usage:
        <x-search-select model="customerSearch" :show="$results->isNotEmpty()"
            :selected="$picked?->name" clear="clearCustomer">
            @foreach ($results as $i => $row)
                <x-search-select.option :index="$i" wire:click="pick({{ $row->id }})"
                    wire:key="row-{{ $row->id }}" :label="$row->name" />
            @endforeach
        </x-search-select>
--}}
@props([
    'model',
    'placeholder' => 'Search…',
    'icon' => 'magnifying-glass',
    'size' => null,
    'show' => false,
    'selected' => null,
    'selectedHint' => null,
    'clear' => null,
    'autofocus' => false,
    'inputRef' => 'input',
])

@php
    $large = $size === 'lg';
@endphp

<div {{ $attributes->class('relative') }} x-data="{
    open: true,
    highlight: 0,
    get items() { return this.$refs.list ? [...this.$refs.list.querySelectorAll('[data-search-result]')] : [] },
    get count() { return this.items.length },
    moveDown() {
        if (this.count > 0) {
            this.highlight = (this.highlight + 1) % this.count;
            this.items[this.highlight]?.scrollIntoView({ block: 'nearest' });
        }
    },
    moveUp() {
        if (this.count > 0) {
            this.highlight = (this.highlight - 1 + this.count) % this.count;
            this.items[this.highlight]?.scrollIntoView({ block: 'nearest' });
        }
    },
    selectCurrent() { const el = this.items[this.highlight]; if (el) el.click(); }
}" x-on:click.outside="open = false">

    @if (filled($selected))
        {{-- Picked value. Same height as the input it replaces, so swapping
             between the two never shifts the layout. --}}
        {{-- Distinct wire:keys on the chip and the input wrapper make Livewire
             swap one for the other. Without them it morphs the old node in
             place, and because the input wrapper is wire:ignore'd that morph is
             skipped, leaving a stale input on screen after a pick. --}}
        <div wire:key="search-{{ $model }}-chip"
            @if ($clear)
                role="button" tabindex="0" title="Change"
                {{-- The whole chip clears, not just the X; focus goes straight
                     back to the search box so a replacement can be typed. --}}
                x-on:click="$wire.call('{{ $clear }}').then(() => $nextTick(() => $root.querySelector('[contenteditable]')?.focus()))"
                x-on:keydown.enter.prevent="$el.click()"
                x-on:keydown.backspace.prevent="$el.click()"
            @endif
            @class([
                'flex items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3 shadow-xs dark:border-white/10 dark:bg-white/10',
                $large ? 'h-12' : 'h-10',
                'cursor-pointer transition-colors hover:border-zinc-300 dark:hover:border-white/20' => $clear,
            ])>
            <div
                class="flex size-7 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-[0.6875rem] font-semibold uppercase text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                {{ Str::substr($selected, 0, 2) }}
            </div>
            <div class="min-w-0 flex-1 leading-tight">
                <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $selected }}</div>
                @if (filled($selectedHint))
                    <div class="truncate text-xs text-zinc-500">{{ $selectedHint }}</div>
                @endif
            </div>
            @if ($clear)
                {{-- Visual only: the click bubbles to the chip, which clears. --}}
                <flux:button icon="x-mark" variant="subtle" size="xs" square type="button" tabindex="-1" />
            @endif
        </div>
    @else
        {{-- An editable div, not an <input>. Edge and Chrome attach their
             "Saved info" autofill to every text input whatever autocomplete
             value it carries, but never to contenteditable, so this is the only
             reliable way to keep that popup off a search box.

             wire:ignore stops Livewire's re-render from wiping what is being
             typed. The value is pushed to the server by hand after a short
             pause, and server-side changes (a clear after picking a result) are
             pulled back in through $wire.$watch. --}}
        <div class="relative" wire:ignore wire:key="search-{{ $model }}-input">
            <flux:icon :name="$icon" variant="outline"
                class="pointer-events-none absolute start-3 top-1/2 size-5 -translate-y-1/2 text-zinc-400/75 dark:text-white/60" />

            <div x-ref="{{ $inputRef }}"
                contenteditable="plaintext-only"
                role="combobox" aria-autocomplete="list" aria-haspopup="listbox"
                x-bind:aria-expanded="open ? 'true' : 'false'"
                tabindex="0" spellcheck="false" autocorrect="off" autocapitalize="off"
                data-flux-control
                data-placeholder="{{ $placeholder }}"
                x-init="
                    const apply = (value) => {
                        value = value ?? '';
                        if ($el.textContent === value) return;
                        // Don't overwrite text mid-typing with an older server echo;
                        // while focused, only a server-side clear is applied.
                        if (document.activeElement === $el && value !== '') return;
                        $el.textContent = value;
                    };
                    apply($wire.get('{{ $model }}'));
                    $wire.$watch('{{ $model }}', apply);
                    @if ($autofocus) $nextTick(() => $el.focus()); @endif
                "
                x-on:focus="open = true"
                x-on:input="
                    open = true; highlight = 0;
                    // Emptied text can leave a stray <br>, which defeats :empty
                    // and hides the placeholder.
                    if ($el.textContent === '') $el.innerHTML = '';
                    clearTimeout($el._syncTimer);
                    $el._syncTimer = setTimeout(() => $wire.set('{{ $model }}', $el.textContent.trim()), 150);
                "
                x-on:paste.prevent="document.execCommand('insertText', false, $event.clipboardData.getData('text/plain').replace(/\s+/g, ' '))"
                x-on:keydown.arrow-down.prevent="open = true; moveDown()"
                x-on:keydown.arrow-up.prevent="open = true; moveUp()"
                x-on:keydown.enter.prevent="selectCurrent(); open = false"
                x-on:keydown.escape="open = false"
                class="block w-full cursor-text overflow-hidden whitespace-nowrap rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white ps-10 {{ isset($trailing) ? 'pe-12' : 'pe-3' }} text-zinc-700 shadow-xs focus:outline-2 focus:-outline-offset-1 focus:outline-[var(--color-accent)] dark:border-white/10 dark:bg-white/10 dark:text-zinc-300 empty:before:pointer-events-none empty:before:text-zinc-400 empty:before:content-[attr(data-placeholder)] {{ $large ? 'h-12 py-3 text-base leading-6' : 'h-10 py-2 text-base leading-[1.375rem] sm:text-sm' }}"></div>

            @isset($trailing)
                <div class="pointer-events-none absolute end-0 top-1/2 flex -translate-y-1/2 items-center pe-2">
                    {{ $trailing }}
                </div>
            @endisset
        </div>

        @if ($show)
            <div x-ref="list" x-show="open"
                class="absolute z-20 mt-1.5 max-h-80 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                {{ $slot }}
            </div>
        @endif
    @endif
</div>
