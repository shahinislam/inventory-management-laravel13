<?php

namespace App\Livewire\Shifts;

use App\Models\CashShift;
use App\Services\ShiftService;
use Livewire\Component;

/**
 * One shift: what went through the drawer, cash in/out, and closing count.
 *
 * Without a {shift} it shows the signed-in cashier's open shift. Managers can
 * open anyone's shift from the list to review it.
 */
class ShiftPanel extends Component
{
    public ?CashShift $shift = null;

    public string $cashDirection = 'out';

    public string $cashAmount = '';

    public string $cashReason = '';

    public string $countedCash = '';

    public string $closeNotes = '';

    public bool $showClose = false;

    public function mount(?CashShift $shift = null): void
    {
        if ($shift?->exists) {
            abort_unless($shift->user_id === auth()->id() || auth()->user()->hasRole(['admin', 'manager']), 403);
            $this->shift = $shift;
        } else {
            $this->shift = app(ShiftService::class)->currentFor(auth()->user());
        }
    }

    private function canOperate(): bool
    {
        return $this->shift?->isOpen() && ($this->shift->user_id === auth()->id() || auth()->user()->hasRole(['admin', 'manager']));
    }

    public function moveCash(): void
    {
        abort_unless($this->canOperate(), 403);

        $this->cashAmount = str_replace(',', '', $this->cashAmount);
        $this->validate([
            'cashDirection' => 'required|in:in,out',
            'cashAmount' => 'required|numeric|min:0.01',
            'cashReason' => 'required|string|max:200',
        ], [
            'cashAmount.required' => 'Enter the amount.',
            'cashReason.required' => 'Say why, e.g. "Change from bank" or "Paid to delivery man".',
        ]);

        app(ShiftService::class)->moveCash($this->shift, $this->cashDirection, (float) $this->cashAmount, $this->cashReason);

        $this->reset('cashAmount', 'cashReason');
        session()->flash('success', 'Drawer updated.');
    }

    public function closeShift(): void
    {
        abort_unless($this->canOperate(), 403);

        $this->countedCash = str_replace(',', '', $this->countedCash);
        $this->validate(
            ['countedCash' => 'required|numeric|min:0', 'closeNotes' => 'nullable|string|max:500'],
            ['countedCash.required' => 'Count the cash in the drawer and enter the total.'],
        );

        $this->shift = app(ShiftService::class)->close($this->shift, (float) $this->countedCash, $this->closeNotes ?: null);
        $this->showClose = false;

        $diff = (float) $this->shift->difference;
        session()->flash($diff == 0.0 ? 'success' : 'error', match (true) {
            $diff == 0.0 => 'Shift closed. The drawer matches exactly.',
            $diff > 0 => 'Shift closed. The drawer has '.money($diff).' more than expected.',
            default => 'Shift closed. The drawer is '.money(-$diff).' short.',
        });
    }

    public function render()
    {
        $summary = $this->shift ? app(ShiftService::class)->summary($this->shift) : null;
        $movements = $this->shift?->expenses()->with('createdBy:id,name')->latest()->get() ?? collect();

        return view('livewire.shifts.shift-panel', compact('summary', 'movements'))
            ->layout('layouts.app', ['title' => $this->shift ? 'Shift '.$this->shift->shift_number : 'My Shift']);
    }
}
