<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('categories.index') }}" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $this->category?->exists ? 'Edit Category' : 'Add Category' }}</flux:heading>
            <flux:text class="mt-1">{{ $this->category?->exists ? 'Update category details' : 'Create a new category' }}</flux:text>
        </div>
    </div>

    <form wire:submit="save">
        <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem">

            {{-- Left Column --}}
            <div class="space-y-6">
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Category Details</flux:heading>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Name <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                            <flux:input wire:model.live="name" placeholder="Category name" />
                            <flux:error name="name" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Slug</flux:label>
                            <flux:input wire:model="slug" placeholder="auto-generated-from-name" />
                            <flux:error name="slug" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Description</flux:label>
                            <flux:textarea wire:model="description" placeholder="Category description..." rows="3" />
                            <flux:error name="description" />
                        </flux:field>
                    </div>
                </flux:card>
            </div>

            {{-- Right Column --}}
            <div class="space-y-6">

                {{-- Category Image --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Category Image</flux:heading>

                    @if($media)
                        <div class="relative">
                            <img src="{{ $media->file_url }}" class="w-full rounded-lg object-cover aspect-square" />
                            <flux:button
                                icon="x-mark"
                                variant="ghost"
                                size="sm"
                                square
                                class="absolute top-2 right-2 bg-white dark:bg-zinc-800"
                                wire:click="removeMedia"
                                type="button"
                            />
                        </div>
                    @else
                        <button
                            type="button"
                            wire:click="$set('showMediaPicker', true)"
                            class="flex w-full flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-zinc-300 p-6 text-zinc-400 hover:border-zinc-400 dark:border-zinc-700"
                        >
                            <flux:icon name="photo" class="size-8" />
                            <flux:text class="text-sm">Click to select image</flux:text>
                        </button>
                    @endif
                </flux:card>

                {{-- Settings --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Settings</flux:heading>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Parent Category</flux:label>
                            <flux:select wire:model="parent_id">
                                <flux:select.option value="">None (Root Category)</flux:select.option>
                                @foreach($categories as $cat)
                                    <flux:select.option value="{{ $cat->id }}">{{ $cat->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="parent_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Display Order</flux:label>
                            <flux:input wire:model="order" type="number" min="0" placeholder="0" />
                            <flux:error name="order" />
                        </flux:field>

                        <flux:field>
                            <div class="flex items-center justify-between">
                                <flux:label>Active</flux:label>
                                <flux:switch wire:model="is_active" />
                            </div>
                        </flux:field>
                    </div>
                </flux:card>

                {{-- Actions --}}
                <flux:card class="p-6">
                    <div class="space-y-3">
                        <flux:button type="submit" class="w-full" icon="check">
                            {{ $this->category?->exists ? 'Update Category' : 'Save Category' }}
                        </flux:button>
                        <flux:button type="button" variant="ghost" class="w-full" href="{{ route('categories.index') }}" wire:navigate>
                            Cancel
                        </flux:button>
                    </div>
                </flux:card>

            </div>
        </div>
    </form>

    {{-- Media Picker Modal --}}
    <flux:modal wire:model="showMediaPicker" class="max-w-4xl">
        <div class="p-6">
            <flux:heading class="mb-4">Select Image</flux:heading>
            <livewire:media.media-picker wire:select-media="selectMedia" />
        </div>
    </flux:modal>

</div>
