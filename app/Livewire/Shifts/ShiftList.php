<?php

namespace App\Livewire\Shifts;

use App\Models\CashShift;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class ShiftList extends Component
{
    use WithPagination;

    public string $userFilter = '';

    public string $statusFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function updating($name): void
    {
        if (in_array($name, ['userFilter', 'statusFilter', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $shifts = CashShift::query()
            ->with(['user:id,name', 'warehouse:id,name'])
            ->when($this->userFilter, fn ($q) => $q->where('user_id', $this->userFilter))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('opened_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('opened_at', '<=', $this->dateTo))
            ->latest('opened_at')
            ->paginate(20);

        $users = User::whereIn('id', CashShift::distinct()->pluck('user_id'))->orderBy('name')->get(['id', 'name']);

        return view('livewire.shifts.shift-list', compact('shifts', 'users'))
            ->layout('layouts.app', ['title' => 'Shifts']);
    }
}
