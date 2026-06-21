<?php

namespace Database\Factories;

use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsAppTemplateFactory extends Factory
{
    protected $model = WhatsAppTemplate::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'label' => fake()->sentence(3),
            'message' => 'Bonjour {client_name}, votre commande {reference} est en cours.',
            'is_active' => true,
        ];
    }
}
