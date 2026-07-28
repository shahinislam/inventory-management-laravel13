<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use App\Models\Media;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Component;

class CategoryForm extends Component
{
    public ?Category $category = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public ?int $parent_id = null;

    public ?int $media_id = null;

    public int $order = 0;

    public bool $is_active = true;

    public bool $showMediaPicker = false;

    protected $listeners = ['select-media' => 'selectMedia'];

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:categories,slug,'.($this->category?->id ?? 'NULL'),
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'media_id' => 'nullable|exists:media,id',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function mount(?Category $category = null): void
    {
        if ($category?->exists) {
            $this->category = $category;
            $this->name = $category->name;
            $this->slug = $category->slug;
            $this->description = $category->description ?? '';
            $this->parent_id = $category->parent_id;
            $this->media_id = $category->media_id;
            $this->order = $category->order;
            $this->is_active = $category->is_active;
        }
    }

    public function updatedName(): void
    {
        if (! $this->category?->exists) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function save(): void
    {
        $data = $this->validate();
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        if ($this->category?->exists) {
            $this->category->update($data);
            $message = 'Category updated successfully!';
        } else {
            Category::create($data);
            $message = 'Category created successfully!';
        }

        Cache::forget('categories_list');
        session()->flash('success', $message);
        $this->redirect(route('categories.index'), navigate: true);
    }

    public function selectMedia(int $mediaId): void
    {
        $this->media_id = $mediaId;
        $this->showMediaPicker = false;
    }

    public function removeMedia(): void
    {
        $this->media_id = null;
    }

    public function render()
    {
        $categories = Category::active()
            ->where('id', '!=', $this->category?->id)
            ->whereNull('parent_id')
            ->ordered()
            ->get();

        $media = $this->media_id ? Media::find($this->media_id) : null;

        return view('livewire.categories.category-form', compact('categories', 'media'))
            ->layout('layouts.app', ['title' => $this->category?->exists ? 'Edit Category' : 'Add Category']);
    }
}
