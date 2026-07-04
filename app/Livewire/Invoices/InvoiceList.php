<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;

class InvoiceList extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $statusFilter = '';
    public string $dateFrom     = '';
    public string $dateTo       = '';

    protected $queryString = [
        'search'       => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function render()
    {
        $invoices = Invoice::query()
            ->with(['customer', 'createdBy'])
            ->withCount('items')
            ->when($this->search, fn($q) => $q
                ->where('invoice_number', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_phone', 'like', "%{$this->search}%")
            )
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->dateFrom, fn($q) => $q->whereDate('invoice_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('invoice_date', '<=', $this->dateTo))
            ->latest()
            ->paginate(15);

        $summary = [
            'total_today'   => Invoice::whereDate('invoice_date', today())->where('status', 'paid')->sum('total'),
            'total_pending' => Invoice::whereIn('status', ['draft', 'sent', 'partial'])->sum('due_amount'),
            'total_overdue' => Invoice::where('status', 'overdue')->count(),
        ];

        return view('livewire.invoices.invoice-list', compact('invoices', 'summary'))
            ->layout('layouts.app', ['title' => 'Invoices']);
    }
}
