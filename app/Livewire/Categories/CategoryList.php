<?php

namespace App\Livewire\Categories;

use App\Concerns\AuthorizesDestructiveActions;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithPagination;

class CategoryList extends Component
{
    use AuthorizesDestructiveActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public ?int $deleteId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
    }

    public function delete(): void
    {
        if (! $this->canDelete()) {
            return;
        }

        $category = Category::findOrFail($this->deleteId);

        // Move children to parent
        $category->children()->update(['parent_id' => $category->parent_id]);

        $category->delete();
        Cache::forget('categories_list');

        $this->deleteId = null;
        session()->flash('success', 'Category deleted successfully!');
    }

    public function toggleStatus(int $id): void
    {
        $category = Category::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);
        Cache::forget('categories_list');
    }

    public function render()
    {
        $categories = Category::query()
            ->with(['parent', 'media', 'children'])
            ->withCount('products')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('is_active', $this->statusFilter === 'active'))
            ->orderBy('order')
            ->paginate(15);

        return view('livewire.categories.category-list', compact('categories'))
            ->layout('layouts.app', ['title' => 'Categories']);
    }
}
