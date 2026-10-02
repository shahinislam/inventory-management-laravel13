<?php

namespace App\Livewire\Membership;

use App\Concerns\AuthorizesDestructiveActions;
use App\Models\MembershipReward;
use App\Models\MembershipRewardRedemption;
use Livewire\Component;
use Livewire\WithPagination;

class RewardList extends Component
{
    use AuthorizesDestructiveActions, WithPagination;

    public string $search = '';

    public string $basisFilter = '';

    public ?int $deleteId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingBasisFilter(): void
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

        // Soft delete: rewards already given keep their history.
        MembershipReward::findOrFail($this->deleteId)->delete();
        $this->deleteId = null;
        session()->flash('success', 'Reward deleted. Rewards already given stay in the history.');
    }

    public function toggleStatus(int $id): void
    {
        $reward = MembershipReward::findOrFail($id);
        $reward->update(['is_active' => ! $reward->is_active]);
    }

    public function render()
    {
        $rewards = MembershipReward::query()
            ->with('giftProduct')
            ->withCount('redemptions')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->basisFilter, fn ($q) => $q->where('basis', $this->basisFilter))
            ->orderBy('basis')
            ->orderBy('min_amount')
            ->paginate(15);

        $stats = [
            'active' => MembershipReward::active()->count(),
            'given' => MembershipRewardRedemption::count(),
            'discount' => (float) MembershipRewardRedemption::sum('discount_amount'),
            'gifts' => (int) MembershipRewardRedemption::where('reward_type', 'gift')->sum('gift_quantity'),
        ];

        $recent = MembershipRewardRedemption::with(['customer', 'invoice', 'giftProduct'])
            ->latest()->limit(8)->get();

        return view('livewire.membership.reward-list', compact('rewards', 'stats', 'recent'))
            ->layout('layouts.app', ['title' => 'Member Rewards']);
    }
}
