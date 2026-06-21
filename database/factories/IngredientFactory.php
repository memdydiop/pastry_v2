<?php

namespace Database\Factories;

use App\Enums\IngredientUnit;
use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

class IngredientFactory extends Factory
{
    protected $model = Ingredient::class;

    public function definition(): array
    {
        $units = IngredientUnit::cases();
        $unit = $units[array_rand($units)];

        return [
            'name' => fake()->word(),
            'unit' => $unit->value,
            'stock_quantity' => fake()->randomFloat(2, 0, 50),
            'alert_threshold' => fake()->randomFloat(2, 1, 10),
            'is_critical' => fake()->boolean(20),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => fake()->randomFloat(2, 0, $attributes['alert_threshold']),
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_critical' => true,
        ]);
    }
}
