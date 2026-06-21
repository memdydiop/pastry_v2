<?php

namespace Database\Factories;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Génoise nature',
                'Génoise chocolat',
                'Génoise vanille',
                'Crème au beurre',
                'Ganache chocolat',
                'Crème mousseline',
                'Pâte à sucre',
                'Sirop d\'imbibage',
                'Glaçage royal',
                'Meringue italienne',
                'Insert fruit rouge',
                'Caramel beurre salé',
                'Mousse chocolat',
                'Biscuit joconde',
                'Crème pâtissière',
            ]),
            'category' => fake()->randomElement([
                'Biscuit', 'Crème', 'Glaçage', 'Garniture', 'Sirop', 'Mousse', 'Insert',
            ]),
            'description' => fake()->optional()->sentence(10),
            'instructions' => fake()->optional()->paragraph(3),
            'expected_cost' => fake()->optional()->randomFloat(2, 500, 15000),
            'is_active' => fake()->boolean(90),
        ];
    }
}
