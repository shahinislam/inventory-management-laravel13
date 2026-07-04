<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Media Library</flux:heading>
            <flux:text class="mt-1">Manage your uploaded files & images</flux:text>
        </div>

        {{-- Upload Button --}}
        <flux:button icon="arrow-up-tray" x-on:click="$refs.fileInput.click()" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="files">Upload Files</span>
            <span wire:loading wire:target="files" class="flex items-center gap-2">
                <flux:icon name="arrow-path" class="size-4 animate-spin" />
                Uploading...
            </span>
        </flux:button>
        <input
            x-ref="fileInput"
            type="file"
            multiple
            accept="image/*,.pdf,.doc,.docx"
            wire:model="files"
            class="hidden"
        />
    </div>

    {{-- Upload Loading Overlay --}}
    <div wire:loading wire:target="files" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="flex flex-col items-center gap-3 rounded-xl bg-white p-8 dark:bg-zinc-900">
            <flux:icon name="arrow-path" class="size-10 animate-spin text-blue-500" />
            <flux:heading>Uploading & Processing...</flux:heading>
            <flux:text class="text-zinc-400">Converting to WebP, generating thumbnails</flux:text>
        </div>
    </div>

    {{-- Success Message --}}
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    {{-- Drag & Drop Zone --}}
    <div
        class="mb-6 rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-700 p-8 text-center transition hover:border-blue-400"
        x-data="{ dragging: false }"
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="
            dragging = false;
            $wire.upload('files', $event.dataTransfer.files)
        "
        :class="dragging ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : ''"
    >
        <flux:icon name="arrow-up-tray" class="mx-auto mb-3 size-10 text-zinc-400" />
        <flux:text class="font-medium">Drag & drop files here</flux:text>
        <flux:text class="text-sm text-zinc-400">or click Upload Files button above</flux:text>
        <flux:text class="mt-1 text-xs text-zinc-400">Supports: JPG, PNG, GIF, WebP, PDF, DOC (max 10MB)</flux:text>
    </div>

    {{-- Toolbar --}}
    <div class="mb-4 flex items-center gap-3">

        {{-- Search --}}
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search files..."
            icon="magnifying-glass"
            class="flex-1"
        />

        {{-- Type Filter --}}
        <flux:select wire:model.live="typeFilter" style="width:140px">
            <flux:select.option value="">All Types</flux:select.option>
            <flux:select.option value="image">Images</flux:select.option>
            <flux:select.option value="document">Documents</flux:select.option>
            <flux:select.option value="video">Videos</flux:select.option>
            <flux:select.option value="other">Other</flux:select.option>
        </flux:select>

        {{-- View Toggle --}}
        <div class="flex rounded-lg border border-zinc-200 dark:border-zinc-700">
            <flux:button icon="squares-2x2" variant="{{ $view === 'grid' ? 'filled' : 'ghost' }}" size="sm" square wire:click="$set('view', 'grid')" />
            <flux:button icon="list-bullet" variant="{{ $view === 'list' ? 'filled' : 'ghost' }}" size="sm" square wire:click="$set('view', 'list')" />
        </div>

        {{-- Bulk Delete --}}
        @if(count($selected) > 0)
            <flux:button icon="trash" variant="danger" wire:click="deleteSelected" wire:confirm="Delete {{ count($selected) }} selected file(s)?">
                Delete ({{ count($selected) }})
            </flux:button>
        @endif

    </div>

    {{-- Grid View --}}
    @if($view === 'grid')
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem">
            @forelse($media as $item)
                <div
                    wire:key="{{ $item->id }}"
                    class="group relative cursor-pointer overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 transition hover:border-blue-400"
                    :class="@js(in_array($item->id, $selected)) ? 'ring-2 ring-blue-500' : ''"
                >
                    {{-- Checkbox --}}
                    <div class="absolute top-2 left-2 z-10">
                        <input
                            type="checkbox"
                            wire:click="toggleSelect({{ $item->id }})"
                            {{ in_array($item->id, $selected) ? 'checked' : '' }}
                            class="rounded"
                        />
                    </div>

                    {{-- Thumbnail --}}
                    <div wire:click="preview({{ $item->id }})" class="aspect-square bg-zinc-100 dark:bg-zinc-800">
                        @if($item->file_type === 'image')
                            <img
                                src="{{ $item->file_url }}"
                                alt="{{ $item->alt_text ?? $item->file_name }}"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                        @else
                            <div class="flex h-full items-center justify-center">
                                <flux:icon name="document" class="size-12 text-zinc-400" />
                            </div>
                        @endif
                    </div>

                    {{-- File Info --}}
                    <div class="p-2">
                        <flux:text class="truncate text-xs font-medium">{{ $item->file_name }}</flux:text>
                        <flux:text class="text-xs text-zinc-400">{{ $item->formattedSize() }}</flux:text>
                    </div>

                    {{-- Hover Actions --}}
                    <div class="absolute inset-0 hidden group-hover:flex items-center justify-center gap-2 bg-black/40 rounded-xl">
                        <flux:button
                            icon="eye"
                            variant="ghost"
                            size="sm"
                            square
                            class="text-white hover:bg-white/20"
                            wire:click="preview({{ $item->id }})"
                        />
                        <flux:button
                            icon="trash"
                            variant="ghost"
                            size="sm"
                            square
                            class="text-red-400 hover:bg-white/20"
                            wire:click="delete({{ $item->id }})"
                            wire:confirm="Delete this file?"
                        />
                    </div>
                </div>
            @empty
                <div class="col-span-full py-16 text-center">
                    <flux:icon name="photo" class="mx-auto mb-3 size-12 text-zinc-300" />
                    <flux:text class="text-zinc-400">No files found</flux:text>
                </div>
            @endforelse
        </div>

    {{-- List View --}}
    @else
        <flux:card>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="w-10">
                        <input type="checkbox" wire:click="toggleSelectAll" {{ $selectAll ? 'checked' : '' }} class="rounded" />
                    </flux:table.column>
                    <flux:table.column class="w-16">Preview</flux:table.column>
                    <flux:table.column>File Name</flux:table.column>
                    <flux:table.column>Type</flux:table.column>
                    <flux:table.column>Size</flux:table.column>
                    <flux:table.column>Dimensions</flux:table.column>
                    <flux:table.column>Uploaded</flux:table.column>
                    <flux:table.column class="text-right">Actions</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($media as $item)
                        <flux:table.row wire:key="{{ $item->id }}">
                            <flux:table.cell>
                                <input type="checkbox" wire:click="toggleSelect({{ $item->id }})" {{ in_array($item->id, $selected) ? 'checked' : '' }} class="rounded" />
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($item->file_type === 'image')
                                    <img src="{{ $item->file_url }}" class="size-10 rounded-lg object-cover" />
                                @else
                                    <div class="flex size-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon name="document" class="size-5 text-zinc-400" />
                                    </div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:text class="text-sm font-medium">{{ $item->file_name }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$item->file_type === 'image' ? 'blue' : 'zinc'">
                                    {{ ucfirst($item->file_type) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:text class="text-sm">{{ $item->formattedSize() }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:text class="text-sm">
                                    {{ $item->width ? $item->width . '×' . $item->height : '-' }}
                                </flux:text>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:text class="text-sm text-zinc-400">{{ $item->created_at->diffForHumans() }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <flux:dropdown align="end">
                                    <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" square />
                                    <flux:menu>
                                        <flux:menu.item icon="eye" wire:click="preview({{ $item->id }})">Preview</flux:menu.item>
                                        <flux:menu.item icon="arrow-down-tray" :href="$item->file_url" target="_blank">Download</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $item->id }})" wire:confirm="Delete this file?">Delete</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="py-12 text-center">
                                <flux:text class="text-zinc-400">No files found</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    {{-- Pagination --}}
    @if($media->hasPages())
        <div class="mt-4">{{ $media->links() }}</div>
    @endif

    {{-- Preview Modal --}}
    @if($previewMedia)
        <flux:modal wire:model="previewId" class="max-w-2xl">
            <div class="p-6">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading>{{ $previewMedia->file_name }}</flux:heading>
                </div>

                {{-- Image Preview --}}
                @if($previewMedia->file_type === 'image')
                    <img
                        src="{{ $previewMedia->file_url }}"
                        alt="{{ $previewMedia->alt_text }}"
                        class="w-full rounded-lg object-contain max-h-96"
                    />
                @else
                    <div class="flex h-48 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon name="document" class="size-16 text-zinc-400" />
                    </div>
                @endif

                {{-- File Details --}}
                <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                        <flux:text class="text-xs text-zinc-400">File Size</flux:text>
                        <flux:text class="font-medium">{{ $previewMedia->formattedSize() }}</flux:text>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                        <flux:text class="text-xs text-zinc-400">Type</flux:text>
                        <flux:text class="font-medium">{{ $previewMedia->mime_type }}</flux:text>
                    </div>
                    @if($previewMedia->width)
                        <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                            <flux:text class="text-xs text-zinc-400">Dimensions</flux:text>
                            <flux:text class="font-medium">{{ $previewMedia->width }}×{{ $previewMedia->height }}px</flux:text>
                        </div>
                    @endif
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                        <flux:text class="text-xs text-zinc-400">Uploaded</flux:text>
                        <flux:text class="font-medium">{{ $previewMedia->created_at->format('d M Y') }}</flux:text>
                    </div>
                </div>

                {{-- URL Copy --}}
                <div class="mt-4">
                    <flux:input
                        value="{{ $previewMedia->file_url }}"
                        readonly
                        label="File URL"
                        x-on:click="$el.querySelector('input').select(); document.execCommand('copy'); $dispatch('notify', {message: 'URL copied!', type: 'success'})"
                    />
                </div>

                <div class="mt-4 flex justify-end gap-3">
                    <flux:button icon="arrow-down-tray" variant="ghost" :href="$previewMedia->file_url" target="_blank">Download</flux:button>
                    <flux:button variant="danger" icon="trash" wire:click="delete({{ $previewMedia->id }})" wire:confirm="Delete this file?">Delete</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

</div>
