<?php

namespace Database\Factories;

use App\Models\DeliveryPartner;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryPartnerFactory extends Factory
{
    protected $model = DeliveryPartner::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => fake()->unique()->phoneNumber(),
            'email' => fake()->email(),
            'vehicle_type' => fake()->randomElement(['Camion', 'Fourgon', 'Moto', 'Vélo', 'Piéton']),
            'base_rate' => fake()->randomFloat(2, 1000, 15000),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
