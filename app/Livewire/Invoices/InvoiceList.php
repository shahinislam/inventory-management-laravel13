<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;

class InvoiceList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    /** Which kind of invoice to list: sales, returns (credit notes) or held POS sales. */
    public string $kind = 'sales';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'kind' => ['except' => 'sales'],
    ];

    public function updatingKind(): void
    {
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function render()
    {
        $invoices = Invoice::query()
            ->when($this->kind === 'returns', fn ($q) => $q->returns()->with('parent:id,invoice_number'))
            ->when($this->kind === 'held', fn ($q) => $q->held())
            ->when(! in_array($this->kind, ['returns', 'held'], true), fn ($q) => $q->sales())
            ->with(['customer', 'createdBy'])
            ->withCount('items')
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('invoice_number', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_phone', 'like', "%{$this->search}%")
            ))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('invoice_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('invoice_date', '<=', $this->dateTo))
            ->latest()
            ->paginate(15);

        $summary = [
            'total_today' => Invoice::sales()->whereDate('invoice_date', today())->where('status', 'paid')->sum('total'),
            'total_pending' => Invoice::sales()->whereIn('status', ['draft', 'sent', 'partial'])->sum('due_amount'),
            'total_overdue' => Invoice::sales()->where('status', 'overdue')->count(),
        ];

        $counts = [
            'returns' => Invoice::query()->returns()->count(),
            'held' => Invoice::held()->count(),
        ];

        return view('livewire.invoices.invoice-list', compact('invoices', 'summary', 'counts'))
            ->layout('layouts.app', ['title' => 'Invoices']);
    }
}
