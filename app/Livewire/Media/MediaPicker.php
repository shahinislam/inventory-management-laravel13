<?php

namespace App\Livewire\Media;

use App\Models\Media;
use App\Services\MediaService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MediaPicker extends Component
{
    use WithFileUploads, WithPagination;

    public array $files = [];

    public string $search = '';

    public string $typeFilter = 'image';

    public ?int $selectedId = null;

    public function updatedFiles(): void
    {
        $this->validate(['files.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp']);

        $service = app(MediaService::class);
        foreach ($this->files as $file) {
            $last = $service->upload($file, auth()->id());
        }

        $this->files = [];
        $this->resetPage();

        if (isset($last)) {
            $this->selectMedia($last->id);
        }
    }

    public function selectMedia(int $id): void
    {
        $this->selectedId = $id;
        $this->dispatch('select-media', mediaId: $id);
    }

    public function render()
    {
        $media = Media::query()
            ->when($this->search, fn ($q) => $q->where('file_name', 'like', "%{$this->search}%"))
            ->when($this->typeFilter, fn ($q) => $q->where('file_type', $this->typeFilter))
            ->latest()
            ->paginate(18);

        return view('livewire.media.media-picker', compact('media'));
    }
}
