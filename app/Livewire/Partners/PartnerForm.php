<?php

namespace App\Livewire\Partners;

use App\Models\Partner;
use Livewire\Component;

class PartnerForm extends Component
{
    public ?Partner $partner = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    // String-typed for the same reason as the money fields elsewhere: a null
    // column would raise a TypeError on a float property.
    public string $share_percentage = '0';

    public bool $is_active = true;

    public string $notes = '';

    public function mount(?Partner $partner = null): void
    {
        if ($partner?->exists) {
            $this->partner = $partner;
            $this->name = $partner->name;
            $this->email = $partner->email ?? '';
            $this->phone = $partner->phone ?? '';
            $this->share_percentage = (string) ($partner->share_percentage ?? '0');
            $this->is_active = (bool) $partner->is_active;
            $this->notes = $partner->notes ?? '';
        }
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:20',
            'share_percentage' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * Share held by everyone else, so the form can show what is left and
     * validate this partner's slice against it.
     */
    public function getOtherShareProperty(): float
    {
        return Partner::totalActiveShare(exceptId: $this->partner?->id);
    }

    public function getResultingShareProperty(): float
    {
        return $this->otherShare + ($this->is_active ? (float) ($this->share_percentage ?: 0) : 0);
    }

    public function save(): void
    {
        $this->validate();

        // Shares must account for the whole business, otherwise profit would be
        // either unallocated or over-allocated when the report splits it.
        if ($this->is_active && round($this->resultingShare, 2) > 100) {
            $this->addError('share_percentage', sprintf(
                'Active shares would total %s%%. Other partners already hold %s%%.',
                rtrim(rtrim(number_format($this->resultingShare, 2), '0'), '.'),
                rtrim(rtrim(number_format($this->otherShare, 2), '0'), '.'),
            ));

            return;
        }

        $data = [
            'name' => $this->name,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'share_percentage' => (float) ($this->share_percentage ?: 0),
            'is_active' => $this->is_active,
            'notes' => $this->notes ?: null,
        ];

        if ($this->partner?->exists) {
            $this->partner->update($data);
            session()->flash('success', 'Partner updated.');
        } else {
            Partner::create($data);
            session()->flash('success', 'Partner added.');
        }

        $this->redirect(route('partners.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.partners.partner-form')
            ->layout('layouts.app', [
                'title' => $this->partner?->exists ? 'Edit Partner' : 'Add Partner',
            ]);
    }
}
