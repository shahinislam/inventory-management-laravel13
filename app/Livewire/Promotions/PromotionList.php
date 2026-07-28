<?php

namespace App\Livewire\Promotions;

use App\Concerns\AuthorizesDestructiveActions;
use App\Models\Promotion;
use Livewire\Component;
use Livewire\WithPagination;

class PromotionList extends Component
{
    use AuthorizesDestructiveActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public ?int $deleteId = null;

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

        Promotion::findOrFail($this->deleteId)->delete();
        $this->deleteId = null;
        session()->flash('success', 'Promotion deleted successfully!');
    }

    public function toggleStatus(int $id): void
    {
        $promo = Promotion::findOrFail($id);
        $promo->update(['is_active' => ! $promo->is_active]);
    }

    public function render()
    {
        $promotions = Promotion::query()
            ->with(['product', 'category'])
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%")
            ))
            ->when($this->statusFilter === 'active', fn ($q) => $q->active())
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->statusFilter === 'expired', fn ($q) => $q->where('ends_at', '<', now()))
            ->latest()
            ->paginate(15);

        return view('livewire.promotions.promotion-list', compact('promotions'))
            ->layout('layouts.app', ['title' => 'Promotions']);
    }
}
