<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'gender' => fake()->randomElement(['M', 'Mme']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
