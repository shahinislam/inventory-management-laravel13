<div>
    {{-- Toolbar --}}
    <div class="mb-4 flex gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search images..."
            icon="magnifying-glass"
            class="flex-1" autocomplete="one-time-code" autocorrect="off" autocapitalize="off" spellcheck="false" name="q-{{ Str::random(10) }}" data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other" />

        {{-- Upload --}}
        <flux:button icon="arrow-up-tray" variant="ghost" size="sm" x-on:click="$refs.pickerInput.click()">Upload</flux:button>
        <input x-ref="pickerInput" type="file" multiple accept="image/*" wire:model="files" class="hidden" />
    </div>

    {{-- Grid --}}
    <div class="grid max-h-100 grid-cols-[repeat(auto-fill,minmax(7.5rem,1fr))] gap-3 overflow-y-auto">
        @forelse($media as $item)
            <div
                wire:key="{{ $item->id }}"
                wire:click="selectMedia({{ $item->id }})"
                @class([
                    'relative cursor-pointer overflow-hidden rounded-lg border-2 transition',
                    'border-blue-500 dark:border-blue-400' => $selectedId === $item->id,
                    'border-transparent hover:border-zinc-300 dark:hover:border-zinc-600' => $selectedId !== $item->id,
                ])
            >
                {{-- Selected Check --}}
                @if($selectedId === $item->id)
                    <div class="absolute top-1 right-1 z-10 flex size-5 items-center justify-center rounded-full bg-blue-500">
                        <flux:icon name="check" class="size-3 text-white" />
                    </div>
                @endif

                <div class="aspect-square bg-zinc-100 dark:bg-zinc-800">
                    @if($item->file_type === 'image')
                        <img
                            src="{{ $item->file_url }}"
                            alt="{{ $item->file_name }}"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                    @else
                        <div class="flex h-full items-center justify-center">
                            <flux:icon name="document" class="size-8 text-zinc-400" />
                        </div>
                    @endif
                </div>

                <div class="p-1">
                    <flux:text class="truncate text-xs">{{ $item->file_name }}</flux:text>
                </div>
            </div>
        @empty
            <div class="col-span-full py-8 text-center">
                <flux:icon name="photo" class="mx-auto mb-2 size-10 text-zinc-300" />
                <flux:text class="text-sm text-zinc-400">No images found</flux:text>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($media->hasPages())
        <div class="mt-3">{{ $media->links() }}</div>
    @endif
</div>
