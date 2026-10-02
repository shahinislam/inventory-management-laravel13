<?php

namespace App\Livewire\Membership;

use App\Models\MembershipReward;
use App\Models\Product;
use Livewire\Component;

class RewardForm extends Component
{
    public ?MembershipReward $reward = null;

    public string $name = '';

    public string $basis = 'single_invoice';

    public string $min_amount = '';

    public string $max_amount = '';

    public string $reward_type = 'percent';

    public string $value = '';

    public ?int $gift_product_id = null;

    public string $gift_quantity = '1';

    public bool $is_active = true;

    public string $notes = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'basis' => 'required|in:cumulative,single_invoice',
            'min_amount' => 'required|numeric|min:0',
            // A cumulative rule is a single threshold, so it has no upper bound.
            'max_amount' => 'nullable|numeric|gte:min_amount',
            'reward_type' => 'required|in:percent,fixed,gift',
            'value' => $this->reward_type === 'gift'
                ? 'nullable'
                : ($this->reward_type === 'percent' ? 'required|numeric|gt:0|max:100' : 'required|numeric|gt:0'),
            'gift_product_id' => $this->reward_type === 'gift' ? 'required|exists:products,id' : 'nullable',
            'gift_quantity' => $this->reward_type === 'gift' ? 'required|integer|min:1' : 'nullable',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Give the reward a name, e.g. "Gold member gift".',
            'min_amount.required' => $this->basis === 'cumulative'
                ? 'Enter the total spend the member must reach.'
                : 'Enter the smallest bill that earns this reward.',
            'max_amount.gte' => 'The upper limit must be at least the lower limit.',
            'value.required' => $this->reward_type === 'percent' ? 'Enter the discount percentage.' : 'Enter the discount amount.',
            'value.gt' => 'The discount must be more than zero.',
            'value.max' => 'A percentage discount cannot be more than 100%.',
            'gift_product_id.required' => 'Choose which product is given as the gift.',
            'gift_quantity.min' => 'Give at least one item.',
        ];
    }

    public function mount(?MembershipReward $reward = null): void
    {
        if ($reward?->exists) {
            $this->reward = $reward;
            $this->name = $reward->name;
            $this->basis = $reward->basis;
            $this->min_amount = (string) $reward->min_amount;
            $this->max_amount = (string) ($reward->max_amount ?? '');
            $this->reward_type = $reward->reward_type;
            $this->value = (string) $reward->value;
            $this->gift_product_id = $reward->gift_product_id;
            $this->gift_quantity = (string) $reward->gift_quantity;
            $this->is_active = $reward->is_active;
            $this->notes = $reward->notes ?? '';
        }
    }

    public function updatedBasis(): void
    {
        if ($this->basis === 'cumulative') {
            $this->max_amount = '';
        }
        $this->resetErrorBag(['min_amount', 'max_amount']);
    }

    /** Unsaved rule built from the form, for the live preview and overlap check. */
    private function draft(): MembershipReward
    {
        $draft = new MembershipReward([
            'name' => $this->name,
            'basis' => $this->basis,
            'min_amount' => is_numeric($this->min_amount) ? $this->min_amount : 0,
            'max_amount' => $this->basis === 'single_invoice' && is_numeric($this->max_amount) ? $this->max_amount : null,
            'reward_type' => $this->reward_type,
            'value' => is_numeric($this->value) ? $this->value : 0,
            'gift_product_id' => $this->gift_product_id,
            'gift_quantity' => max(1, (int) $this->gift_quantity),
        ]);
        $draft->setRelation('giftProduct', $this->gift_product_id ? Product::find($this->gift_product_id) : null);

        return $draft;
    }

    /** Other active rules whose range overlaps this one — a warning, not an error. */
    public function getOverlapsProperty()
    {
        if (! is_numeric($this->min_amount)) {
            return collect();
        }

        $min = (float) $this->min_amount;
        $max = $this->basis === 'single_invoice' && is_numeric($this->max_amount) ? (float) $this->max_amount : null;

        return MembershipReward::active()
            ->where('basis', $this->basis)
            ->when($this->reward?->exists, fn ($q) => $q->whereKeyNot($this->reward->id))
            ->get()
            ->filter(function (MembershipReward $other) use ($min, $max) {
                if ($this->basis === 'cumulative') {
                    return (float) $other->min_amount === $min;
                }
                $otherMax = $other->max_amount !== null ? (float) $other->max_amount : INF;

                return $min <= $otherMax && (float) $other->min_amount <= ($max ?? INF);
            })
            ->values();
    }

    public function save(): void
    {
        $data = $this->validate();

        $isGift = $data['reward_type'] === 'gift';
        $data['max_amount'] = $data['basis'] === 'single_invoice' && $data['max_amount'] !== '' && $data['max_amount'] !== null
            ? (float) $data['max_amount'] : null;
        $data['value'] = $isGift ? 0 : (float) $data['value'];
        $data['gift_product_id'] = $isGift ? $data['gift_product_id'] : null;
        $data['gift_quantity'] = $isGift ? (int) $data['gift_quantity'] : 1;
        $data['notes'] = $data['notes'] ?: null;

        if ($this->reward?->exists) {
            $this->reward->update($data);
            $message = 'Reward updated.';
        } else {
            MembershipReward::create($data);
            $message = 'Reward created. It will be offered at the POS to members who qualify.';
        }

        session()->flash('success', $message);
        $this->redirect(route('membership-rewards.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.membership.reward-form', [
            'products' => Product::active()->orderBy('name')->get(['id', 'name', 'sku', 'selling_price']),
            'preview' => $this->draft(),
        ])->layout('layouts.app', ['title' => $this->reward?->exists ? 'Edit Reward' : 'New Reward']);
    }
}
