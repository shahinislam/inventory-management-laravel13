<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Promo',
            'type' => 'percentage',
            'value' => 10,
            'used_count' => 0,
            'is_active' => true,
        ];
    }

    public function fixed(float $value): static
    {
        return $this->state(fn () => ['type' => 'fixed', 'value' => $value]);
    }

    public function percentage(float $value): static
    {
        return $this->state(fn () => ['type' => 'percentage', 'value' => $value]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn () => ['usage_limit' => 5, 'used_count' => 5]);
    }
}
