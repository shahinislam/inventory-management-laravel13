<?php

namespace App\Livewire\Partners;

use App\Concerns\AuthorizesDestructiveActions;
use App\Models\Partner;
use Livewire\Component;
use Livewire\WithPagination;

class PartnerList extends Component
{
    use AuthorizesDestructiveActions, WithPagination;

    public string $search = '';

    public ?int $deleteId = null;

    /** Partner records are admin-only; managers must not remove them. */
    protected function rolesAllowedToDelete(): array
    {
        return ['admin'];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete(): void
    {
        if (! $this->canDelete()) {
            $this->deleteId = null;

            return;
        }

        Partner::find($this->deleteId)?->delete();
        $this->deleteId = null;
        session()->flash('success', 'Partner removed.');
    }

    public function render()
    {
        $partners = Partner::query()
            ->when($this->search, fn ($q) => $q
                ->where(fn ($w) => $w
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")))
            ->withSum(['transactions as invested' => fn ($q) => $q->where('type', 'investment')], 'amount')
            ->withSum(['transactions as withdrawn' => fn ($q) => $q->where('type', 'withdrawal')], 'amount')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.partners.partner-list', [
            'partners' => $partners,
            'totalShare' => Partner::totalActiveShare(),
        ])->layout('layouts.app', ['title' => 'Partners']);
    }
}
