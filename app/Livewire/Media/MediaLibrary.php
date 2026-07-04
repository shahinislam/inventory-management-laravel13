<?php

namespace App\Livewire\Media;

use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MediaLibrary extends Component
{
    use WithFileUploads, WithPagination;

    public array $files       = [];
    public string $search     = '';
    public string $typeFilter = '';
    public string $view       = 'grid';
    public array $selected    = [];
    public bool $selectAll    = false;
    public ?int $previewId    = null;
    public bool $uploading    = false;

    public function updatedFiles(): void
    {
        $this->validate([
            'files.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx',
        ]);

        $service = app(MediaService::class);
        $count   = 0;

        foreach ($this->files as $file) {
            $service->upload($file, auth()->id());
            // Delete tmp file immediately after processing
            Storage::disk('local')->delete('livewire-tmp/' . $file->getFilename());
            $count++;
        }

        $this->files     = [];
        $this->uploading = false;
        $this->resetPage();

        session()->flash('success', "{$count} file(s) uploaded successfully!");
    }

    public function toggleSelect(int $id): void
    {
        if (in_array($id, $this->selected)) {
            $this->selected = array_values(array_filter($this->selected, fn($s) => $s !== $id));
        } else {
            $this->selected[] = $id;
        }
    }

    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selected  = [];
            $this->selectAll = false;
        } else {
            $this->selected  = Media::pluck('id')->toArray();
            $this->selectAll = true;
        }
    }

    public function deleteSelected(): void
    {
        $service = app(MediaService::class);
        $media   = Media::whereIn('id', $this->selected)->get();

        foreach ($media as $item) {
            $service->delete($item);
        }

        $count          = count($this->selected);
        $this->selected = [];
        $this->selectAll = false;
        $this->dispatch('notify', message: "{$count} file(s) deleted!", type: 'success');
    }

    public function delete(int $id): void
    {
        $media = Media::findOrFail($id);
        app(MediaService::class)->delete($media);
        $this->previewId = null;
        $this->dispatch('notify', message: 'File deleted!', type: 'success');
    }

    public function preview(int $id): void
    {
        $this->previewId = $id;
    }

    public function render()
    {
        $media = Media::query()
            ->when($this->search, fn($q) => $q
                ->where('file_name', 'like', "%{$this->search}%")
                ->orWhere('title', 'like', "%{$this->search}%")
            )
            ->when($this->typeFilter, fn($q) => $q->where('file_type', $this->typeFilter))
            ->latest()
            ->paginate(24);

        $previewMedia = $this->previewId ? Media::find($this->previewId) : null;

        return view('livewire.media.media-library', compact('media', 'previewMedia'))
            ->layout('layouts.app', ['title' => 'Media Library']);
    }
}
