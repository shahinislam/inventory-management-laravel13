<?php

namespace App\Services;

/**
 * The per-unit discount that applies to one product, and where it came from.
 *
 * `promotionId` is null when the discount came from the product's own discount
 * field rather than a promotion — only promotion-backed discounts consume a
 * usage count at checkout.
 */
class ResolvedDiscount
{
    public function __construct(
        public readonly float $perUnit,
        public readonly ?int $promotionId = null,
        public readonly ?string $label = null,
    ) {}

    public function fromPromotion(): bool
    {
        return $this->promotionId !== null;
    }
}
