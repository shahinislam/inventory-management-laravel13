<?php

namespace App\Livewire\Pos\Concerns;

use App\Models\CashShift;
use App\Services\ShiftService;

/**
 * The POS only sells inside an open cash shift, so every sale and payment can
 * be traced to a cashier's drawer and counted at close.
 */
trait RequiresShift
{
    public string $openingCash = '';

    public function getCurrentShiftProperty(): ?CashShift
    {
        return app(ShiftService::class)->currentFor(auth()->user());
    }

    public function openShift(): void
    {
        $this->openingCash = str_replace(',', '', $this->openingCash);

        $this->validate(
            ['openingCash' => 'required|numeric|min:0'],
            ['openingCash.required' => 'Count the cash in the drawer and enter it (0 if empty).'],
        );

        app(ShiftService::class)->open(auth()->user(), $this->warehouse_id, (float) $this->openingCash);

        $this->openingCash = '';
        unset($this->currentShift);
        session()->flash('success', 'Shift opened. You can start selling.');
    }

    /** Guard for actions that take money. Flashes and returns null when closed. */
    protected function shiftOrFail(): ?CashShift
    {
        $shift = $this->currentShift;

        if (! $shift) {
            session()->flash('error', 'Open a shift before selling.');
        }

        return $shift;
    }
}
