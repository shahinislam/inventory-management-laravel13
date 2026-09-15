<div class="p-4">

    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('promotions.index') }}" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $this->promotion?->exists ? 'Edit Promotion' : 'New Promotion' }}</flux:heading>
            <flux:text class="mt-1">Set up a discount or special offer</flux:text>
        </div>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_20rem]">

            <div class="space-y-6">

                <flux:card class="p-6">
                    <flux:heading class="mb-4">Promotion Details</flux:heading>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Name <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                                <flux:input wire:model="name" placeholder="e.g. Summer Sale" />
                                <flux:error name="name" />
                            </flux:field>
                            <flux:field>
                                <flux:label>Coupon Code</flux:label>
                                <flux:input wire:model="code" placeholder="Leave empty for auto-apply" />
                                <flux:error name="code" />
                            </flux:field>
                        </div>
                        <flux:field>
                            <flux:label>Description</flux:label>
                            <flux:textarea wire:model="description" rows="2" placeholder="Description of the offer..." />
                        </flux:field>
                    </div>
                </flux:card>

                <flux:card class="p-6">
                    <flux:heading class="mb-4">Discount Configuration</flux:heading>
                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Discount Type</flux:label>
                            <flux:radio.group wire:model.live="type" variant="segmented">
                                <flux:radio value="percentage" label="% Off" />
                                <flux:radio value="fixed" label="Flat Amount" />
                                <flux:radio value="buy_x_get_y" label="Buy X Get Y" />
                            </flux:radio.group>
                        </flux:field>

                        @if($type === 'buy_x_get_y')
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <flux:field>
                                    <flux:label>Buy Quantity</flux:label>
                                    <flux:input wire:model="buy_quantity" type="number" min="1" placeholder="e.g. 3" />
                                </flux:field>
                                <flux:field>
                                    <flux:label>Get Free Quantity</flux:label>
                                    <flux:input wire:model="get_quantity" type="number" min="1" placeholder="e.g. 1" />
                                </flux:field>
                            </div>
                        @else
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <flux:field>
                                    <flux:label>Value <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                                    <flux:input wire:model="value" type="number" step="0.01" min="0" :suffix="$type === 'percentage' ? '%' : null" :prefix="$type === 'fixed' ? '৳' : null" />
                                    <flux:error name="value" />
                                </flux:field>
                                @if($type === 'percentage')
                                    <flux:field>
                                        <flux:label>Max Discount Cap</flux:label>
                                        <flux:input wire:model="max_discount" type="number" step="0.01" min="0" prefix="৳" placeholder="No limit" />
                                    </flux:field>
                                @endif
                            </div>
                        @endif

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Minimum Order Amount</flux:label>
                                <flux:input wire:model="min_order_amount" type="number" step="0.01" min="0" prefix="৳" placeholder="No minimum" />
                            </flux:field>
                            <flux:field>
                                <flux:label>Minimum Quantity</flux:label>
                                <flux:input wire:model="min_quantity" type="number" min="0" placeholder="No minimum" />
                            </flux:field>
                        </div>
                    </div>
                </flux:card>

                <flux:card class="p-6">
                    <flux:heading class="mb-4">Apply To</flux:heading>
                    <flux:field class="mb-4">
                        <flux:radio.group wire:model.live="applyTo" variant="segmented">
                            <flux:radio value="all" label="All Products" />
                            <flux:radio value="product" label="Specific Product" />
                            <flux:radio value="category" label="Specific Category" />
                        </flux:radio.group>
                    </flux:field>

                    @if($applyTo === 'product')
                        <flux:field>
                            <flux:label>Select Product</flux:label>
                            <flux:select wire:model="product_id">
                                <flux:select.option value="">Choose product</flux:select.option>
                                @foreach($products as $p)
                                    <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    @elseif($applyTo === 'category')
                        <flux:field>
                            <flux:label>Select Category</flux:label>
                            <flux:select wire:model="category_id">
                                <flux:select.option value="">Choose category</flux:select.option>
                                @foreach($categories as $c)
                                    <flux:select.option value="{{ $c->id }}">{{ $c->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    @endif
                </flux:card>

            </div>

            <div class="space-y-6">

                <flux:card class="p-6">
                    <flux:heading class="mb-4">Validity Period</flux:heading>
                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Starts At</flux:label>
                            <flux:input wire:model="starts_at" type="datetime-local" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Ends At</flux:label>
                            <flux:input wire:model="ends_at" type="datetime-local" />
                            <flux:error name="ends_at" />
                        </flux:field>
                    </div>
                </flux:card>

                <flux:card class="p-6">
                    <flux:heading class="mb-4">Usage Limit</flux:heading>
                    <flux:field>
                        <flux:label>Max Uses</flux:label>
                        <flux:input wire:model="usage_limit" type="number" min="0" placeholder="Unlimited" />
                    </flux:field>
                </flux:card>

                <flux:card class="p-6">
                    <flux:field>
                        <div class="flex items-center justify-between">
                            <flux:label>Active</flux:label>
                            <flux:switch wire:model="is_active" />
                        </div>
                    </flux:field>
                </flux:card>

                <flux:card class="p-6">
                    <div class="space-y-3">
                        <flux:button type="submit" class="w-full" icon="check">
                            {{ $this->promotion?->exists ? 'Update Promotion' : 'Save Promotion' }}
                        </flux:button>
                        <flux:button type="button" variant="ghost" class="w-full" href="{{ route('promotions.index') }}" wire:navigate>Cancel</flux:button>
                    </div>
                </flux:card>

            </div>
        </div>
    </form>

</div>
