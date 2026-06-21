<?php

namespace Database\Factories;

use App\Models\Experience;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Experience>
 */
class ExperienceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->jobTitle(),
            'company' => fake()->company(),
            'description' => fake()->paragraph(),
            'start_date' => fake()->date('Y-m-d', '-5 years'),
            'end_date' => fake()->date('Y-m-d', '-1 day'),
            'is_current' => false,
            'sort_order' => 0,
        ];
    }
}
