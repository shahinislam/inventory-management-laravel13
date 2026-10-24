<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\Setting;

/**
 * Decides what a customer is actually charged for a product.
 *
 * This lives in one place on purpose: the POS terminal and the invoice form
 * both price the same catalogue, and if they ever disagree the two documents
 * would charge different amounts for the same item.
 */
class PricingService
{
    /** Settings → Tax: whether selling prices already contain tax. */
    public static function pricesIncludeTax(): bool
    {
        return Setting::bool('tax.prices_include_tax');
    }

    /**
     * Split one sale line into its net value, tax and the amount charged.
     *
     * The per-unit discount is taken off before tax. When prices exclude tax
     * the tax is added on top; when they include it, the tax is extracted from
     * the shelf price. Tax is rounded per line, so net + tax = gross exactly
     * and the lines always add up to the invoice header.
     *
     * Every place that prices a sale (POS, held sales, the invoice form) goes
     * through this, and the JavaScript copies in the POS and invoice-form views
     * mirror it for instant totals.
     *
     * @return array{net: float, tax: float, gross: float}
     */
    public static function lineAmounts(float $price, float $discount, float $quantity, float $taxRate, bool $inclusive): array
    {
        $amount = round(($price - $discount) * $quantity, 2);

        if ($inclusive) {
            $tax = round($amount * $taxRate / (100 + $taxRate), 2);

            return ['net' => round($amount - $tax, 2), 'tax' => $tax, 'gross' => $amount];
        }

        $tax = round($amount * $taxRate / 100, 2);

        return ['net' => $amount, 'tax' => $tax, 'gross' => round($amount + $tax, 2)];
    }

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
