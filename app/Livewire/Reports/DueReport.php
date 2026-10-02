<?php

namespace App\Livewire\Reports;

use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Outstanding balances both ways: what customers owe the shop (receivable)
 * and what the shop owes suppliers (payable).
 */
class DueReport extends Component
{
    use WithPagination;

    /** customers | suppliers */
    public string $tab = 'customers';

    public string $search = '';

    public bool $overdueOnly = false;

    protected $queryString = [
        'tab' => ['except' => 'customers'],
        'search' => ['except' => ''],
        'overdueOnly' => ['except' => false],
    ];

    public function updating($name): void
    {
        if (in_array($name, ['tab', 'search', 'overdueOnly'], true)) {
            $this->resetPage();
        }
    }

    private function customerDues()
    {
        return Invoice::sales()
            ->withDue()
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_phone', 'like', "%{$this->search}%")
                ->orWhere('invoice_number', 'like', "%{$this->search}%")
            ))
            ->when($this->overdueOnly, fn ($q) => $q->whereDate('due_date', '<', today()));
    }

    private function supplierDues()
    {
        return PurchaseOrder::query()
            ->withDue()
            ->with('supplier')
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('order_number', 'like', "%{$this->search}%")
                ->orWhereHas('supplier', fn ($sup) => $sup
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%"))
            ))
            ->when($this->overdueOnly, fn ($q) => $q->whereDate('payment_due_date', '<', today()));
    }

    public function render()
    {
        // Headline figures ignore the search so they always show the full picture.
        $summary = [
            'receivable' => (float) Invoice::withDue()->sum('due_amount'),
            'receivable_overdue' => (float) Invoice::withDue()->whereDate('due_date', '<', today())->sum('due_amount'),
            'payable' => (float) PurchaseOrder::withDue()->selectRaw('COALESCE(SUM(total - returned_amount - paid_amount), 0) as due')->value('due'),
            'payable_overdue' => (float) PurchaseOrder::withDue()->whereDate('payment_due_date', '<', today())
                ->selectRaw('COALESCE(SUM(total - returned_amount - paid_amount), 0) as due')->value('due'),
        ];

        if ($this->tab === 'suppliers') {
            $query = $this->supplierDues();
            $filteredTotal = (float) (clone $query)->selectRaw('COALESCE(SUM(total - returned_amount - paid_amount), 0) as due')->value('due');
            $rows = $query
                ->orderByRaw('payment_due_date IS NULL, payment_due_date')
                ->orderBy('order_date')
                ->paginate(20);
        } else {
            $query = $this->customerDues();
            $filteredTotal = (float) (clone $query)->sum('due_amount');
            $rows = $query
                ->orderByRaw('due_date IS NULL, due_date')
                ->orderBy('invoice_date')
                ->paginate(20);
        }

        return view('livewire.reports.due-report', compact('summary', 'rows', 'filteredTotal'))
            ->layout('layouts.app', ['title' => 'Due Report']);
    }
}
