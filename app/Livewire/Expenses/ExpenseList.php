<?php

namespace App\Livewire\Expenses;

use App\Concerns\AuthorizesDestructiveActions;
use App\Concerns\ResolvesReportPeriod;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class ExpenseList extends Component
{
    use AuthorizesDestructiveActions, ResolvesReportPeriod, WithPagination;

    public string $search = '';

    public string $categoryFilter = '';

    public string $methodFilter = '';

    /** Also list till cash in / cash out moves made from the shift screen. */
    public bool $showDrawerMoves = false;

    public ?int $deleteId = null;

    public function updating(string $name): void
    {
        if (in_array($name, ['search', 'categoryFilter', 'methodFilter', 'showDrawerMoves', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
    }

    public function delete(): void
    {
        if (! $this->canDelete()) {
            $this->deleteId = null;

            return;
        }

        Expense::findOrFail($this->deleteId)->delete();
        $this->deleteId = null;
        session()->flash('success', 'Expense deleted successfully!');
    }

    /** Filters shared by the table and the header tiles (type excluded). */
    private function filtered(): Builder
    {
        [$from, $to] = $this->periodRange();

        return Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('expense_number', 'like', "%{$this->search}%")
                ->orWhere('category', 'like', "%{$this->search}%")
                ->orWhere('paid_to', 'like', "%{$this->search}%")
                ->orWhere('notes', 'like', "%{$this->search}%")
                ->orWhere('reference', 'like', "%{$this->search}%")
            ))
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->when($this->methodFilter, fn ($q) => $q->where('method', $this->methodFilter));
    }

    public function render()
    {
        $expenses = $this->filtered()
            ->when(! $this->showDrawerMoves, fn ($q) => $q->costs())
            ->with(['paymentAccount', 'media', 'shift'])
            ->latest('expense_date')
            ->latest('id')
            ->paginate(15);

        // Tiles count real costs only, whatever the drawer toggle says.
        $costs = $this->filtered()->costs();
        $topCategory = (clone $costs)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->first();

        $summary = [
            'total' => (float) (clone $costs)->sum('amount'),
            'count' => (clone $costs)->count(),
            'top_category' => $topCategory?->category,
            'top_total' => (float) ($topCategory?->total ?? 0),
        ];

        $categories = Expense::query()->costs()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('livewire.expenses.expense-list', [
            'expenses' => $expenses,
            'summary' => $summary,
            'categories' => $categories,
            'methods' => Payment::METHODS,
        ])->layout('layouts.app', ['title' => 'Expenses']);
    }
}
