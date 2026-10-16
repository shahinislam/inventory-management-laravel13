<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;

/**
 * Decides what a customer is actually charged for a product.
 *
 * This lives in one place on purpose: the POS terminal and the invoice form
 * both price the same catalogue, and if they ever disagree the two documents
 * would charge different amounts for the same item.
 */
class PricingService
{
    /**
     * Resolve the per-unit discount for a product.
     *
     * An active promotion — product-specific or category-wide — always takes
     * priority over the product's own flat/percentage discount field. A
     * product-specific promotion beats a category-wide one.
     */
    public function resolveDiscount(Product $product): ResolvedDiscount
    {
        $promotion = $this->bestPromotionFor($product);

        if ($promotion) {
            return new ResolvedDiscount(
                perUnit: $this->promotionDiscount($promotion, $product),
                promotionId: $promotion->id,
                label: $promotion->name,
            );
        }

        return new ResolvedDiscount(perUnit: $this->productDiscount($product));
    }

    /**
     * The highest-priority active promotion that still has usage headroom.
     */
    private function bestPromotionFor(Product $product): ?Promotion
    {
        return Promotion::active()
            // A bare orWhere('category_id', null) would become "category_id IS NULL"
            // and hand an uncategorised product every other product's promotion.
            ->where(fn ($q) => $q
                ->where('product_id', $product->id)
                ->when($product->category_id, fn ($q) => $q->orWhere(fn ($q) => $q
                    ->whereNull('product_id')
                    ->where('category_id', $product->category_id)))
                // "All Products" promotions carry neither a product nor a category.
                ->orWhere(fn ($q) => $q->whereNull('product_id')->whereNull('category_id')))
            ->where(fn ($q) => $q
                ->whereNull('usage_limit')
                ->orWhereColumn('used_count', '<', 'usage_limit'))
            // Most specific wins: this product, then its category, then store-wide.
            ->orderByRaw('CASE WHEN product_id = ? THEN 0 WHEN category_id IS NOT NULL THEN 1 ELSE 2 END', [$product->id])
            ->orderBy('id')
            ->first();
    }

    /** Per-unit discount from a promotion, capped by its max_discount. */
    private function promotionDiscount(Promotion $promotion, Product $product): float
    {
        $discount = $promotion->type === 'fixed'
            ? (float) $promotion->value
            : (float) $product->selling_price * ((float) $promotion->value / 100);

        if ($promotion->max_discount) {
            $discount = min($discount, (float) $promotion->max_discount);
        }

        return $discount;
    }

    /** Per-unit discount from the product's own discount field. */
    private function productDiscount(Product $product): float
    {
        return $product->discount_type === 'fixed'
            ? (float) $product->discount
            : (float) $product->selling_price * ((float) $product->discount / 100);
    }
}
