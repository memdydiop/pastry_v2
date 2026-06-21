<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Supplier;
use App\Models\User;
use App\Enums\InventoryMovementType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (!User::where('is_super_admin', true)->exists()) {
            User::create([
                'name' => 'Super Admin',
                'email' => 'super-admin@pastrysaas.com',
                'password' => Hash::make(Str::random(32)),
                'is_super_admin' => true,
                'is_active' => true,
            ]);
        }

        $this->call(RoleAndPermissionSeeder::class);

        $user = User::firstOrCreate(
            ['email' => 'ghost@user.com'],
            [
                'name' => 'Ghost User',
                'password' => bcrypt('R00t7414'),
                'setup_completed_at' => now(),
            ]
        );
        $user->assignRole('ghost');

        $user = User::firstOrCreate(
            ['email' => 'gerant@patisserie.com'],
            [
                'name' => 'Responsable Pâtisserie',
                'password' => bcrypt('R00t7414'),
                'setup_completed_at' => now(),
            ]
        );
        $user->assignRole('Gérant/Admin');

        User::firstOrCreate(
            ['email' => 'nouveau@patisserie.com'],
            [
                'name' => 'Nouvel Employé',
                'password' => bcrypt('R00t7414'),
                'is_active' => false,
                'setup_token' => Str::random(64),
                'setup_token_sent_at' => now(),
                'setup_completed_at' => null,
            ]
        )->assignRole('Pâtissier');

        $this->seedIngredients();
        $this->seedSuppliers();
        $this->seedClients();
        $this->seedRecipes();
        $this->seedStockConsumption();

        $this->call(OrderTestDataSeeder::class);
    }

    private function seedIngredients(): void
    {
        $items = [
            ['name' => 'Farine de blé T55', 'unit' => 'kg', 'stock' => 25.0, 'alert' => 5.0, 'critical' => false, 'price' => 600.0],
            ['name' => 'Sucre semoule', 'unit' => 'kg', 'stock' => 20.0, 'alert' => 5.0, 'critical' => false, 'price' => 700.0],
            ['name' => 'Beurre doux 82% MG', 'unit' => 'kg', 'stock' => 0.5, 'alert' => 2.0, 'critical' => true, 'price' => 5000.0],
            ['name' => 'Œufs frais calibre M', 'unit' => 'unité', 'stock' => 180.0, 'alert' => 30.0, 'critical' => false, 'price' => 100.0],
            ['name' => 'Lait entier', 'unit' => 'L', 'stock' => 12.0, 'alert' => 3.0, 'critical' => false, 'price' => 1000.0],
            ['name' => 'Crème liquide entière 35% MG', 'unit' => 'L', 'stock' => 10.0, 'alert' => 2.0, 'critical' => false, 'price' => 3500.0],
            ['name' => 'Chocolat noir 70%', 'unit' => 'kg', 'stock' => 8.0, 'alert' => 2.0, 'critical' => false, 'price' => 8000.0],
            ['name' => 'Chocolat blanc', 'unit' => 'kg', 'stock' => 0.8, 'alert' => 1.0, 'critical' => false, 'price' => 9000.0],
            ['name' => 'Gousse de vanille', 'unit' => 'unité', 'stock' => 30.0, 'alert' => 6.0, 'critical' => false, 'price' => 1500.0],
            ['name' => 'Levure chimique', 'unit' => 'kg', 'stock' => 0.15, 'alert' => 0.2, 'critical' => false, 'price' => 3000.0],
            ['name' => 'Sel fin', 'unit' => 'kg', 'stock' => 2.0, 'alert' => 0.5, 'critical' => false, 'price' => 500.0],
            ['name' => "Poudre d'amande", 'unit' => 'kg', 'stock' => 5.0, 'alert' => 1.0, 'critical' => false, 'price' => 12000.0],
            ['name' => 'Poudre de noisette', 'unit' => 'kg', 'stock' => 0.3, 'alert' => 0.5, 'critical' => false, 'price' => 14000.0],
            ['name' => 'Purée de framboise', 'unit' => 'kg', 'stock' => 4.0, 'alert' => 1.0, 'critical' => false, 'price' => 10000.0],
            ['name' => 'Sucre glace', 'unit' => 'kg', 'stock' => 8.0, 'alert' => 2.0, 'critical' => false, 'price' => 1200.0],
            ['name' => 'Cacao en poudre', 'unit' => 'kg', 'stock' => 3.0, 'alert' => 0.5, 'critical' => false, 'price' => 5000.0],
            ['name' => 'Beurre de cacao', 'unit' => 'kg', 'stock' => 2.0, 'alert' => 0.5, 'critical' => false, 'price' => 15000.0],
            ['name' => 'Praliné 50% noisette', 'unit' => 'kg', 'stock' => 4.0, 'alert' => 1.0, 'critical' => false, 'price' => 12000.0],
            ['name' => 'Gélatine en feuilles', 'unit' => 'unité', 'stock' => 60.0, 'alert' => 12.0, 'critical' => false, 'price' => 150.0],
            ['name' => 'Colorant alimentaire gel', 'unit' => 'unité', 'stock' => 15.0, 'alert' => 3.0, 'critical' => false, 'price' => 2500.0],
        ];

        $updated = 0;
        $created = 0;
        foreach ($items as $item) {
            $ing = Ingredient::where('name', $item['name'])->first();
            if ($ing) {
                $ing->update([
                    'unit' => $item['unit'],
                    'stock_quantity' => $item['stock'],
                    'alert_threshold' => $item['alert'],
                    'is_critical' => $item['critical'],
                    'unit_price' => $item['price'],
                ]);
                $updated++;
            } else {
                Ingredient::create([
                    'name' => $item['name'],
                    'unit' => $item['unit'],
                    'stock_quantity' => $item['stock'],
                    'alert_threshold' => $item['alert'],
                    'is_critical' => $item['critical'],
                    'unit_price' => $item['price'],
                ]);
                $created++;
            }
        }

        $this->command?->info("✓ {$created} ingrédients créés, {$updated} mis à jour");

        $this->command?->info('✓ '.Ingredient::count().' ingrédients créés');
    }

    private function seedSuppliers(): void
    {
        $items = [
            // Fournisseurs spécialisés
            ['name' => 'Abidjan Distribution Alimentaire', 'category' => 'fournisseur', 'contact' => 'Kouamé N\'Guessan', 'phone' => '+225 27 22 45 67 89', 'email' => 'commandes@abidjandistrib.ci', 'address' => 'ZI de Koumassi, Abidjan'],
            ['name' => 'Côte Farine & Céréales SARL', 'category' => 'fournisseur', 'contact' => 'Mamadou Traoré', 'phone' => '+225 27 22 58 12 34', 'email' => 'contact@cotesfarine.ci', 'address' => 'Yopougon, Abidjan'],
            ['name' => 'Laiterie Tropicale de CI', 'category' => 'fournisseur', 'contact' => 'Ahmed Ouattara', 'phone' => '+225 07 08 48 23 19', 'email' => 'info@laiterietropicale.ci', 'address' => 'Marcory Zone 4, Abidjan'],
            ['name' => 'Chocolats & Gourmandises Import', 'category' => 'fournisseur', 'contact' => 'Aïssata Koné', 'phone' => '+225 05 04 12 45 67', 'email' => 'chocolat.import@orange.ci', 'address' => 'Plateau, Abidjan'],
            // Supermarchés
            ['name' => 'Auchan Cap Sud', 'category' => 'supermarché', 'contact' => '', 'phone' => '+225 27 22 48 00 00', 'address' => 'Cap Sud, Marcory, Abidjan'],
            ['name' => 'AB Center Marcory', 'category' => 'supermarché', 'contact' => '', 'phone' => '+225 27 22 44 44 44', 'address' => 'Marcory, Abidjan'],
            ['name' => 'Prima Centre Koumassi', 'category' => 'supermarché', 'contact' => '', 'phone' => '+225 27 21 25 55 55', 'address' => 'Koumassi, Abidjan'],
            // Marchés
            ['name' => 'Marché de Treichville', 'category' => 'marché', 'contact' => '', 'phone' => '+225 01 00 00 01 01'],
            ['name' => 'Marché de Cocody', 'category' => 'marché', 'contact' => '', 'phone' => '+225 01 00 00 02 02'],
            // Boutiques de quartier
            ['name' => 'Boutique du Coin - Yopougon', 'category' => 'boutique', 'contact' => 'Mamadou Koné', 'phone' => '+225 07 07 07 07 01'],
            ['name' => 'Boutique Alimentation Générale', 'category' => 'boutique', 'contact' => '', 'phone' => '+225 05 05 05 05 01'],
            // Grossistes
            ['name' => 'Grossiste Farine & Sucre - Koumassi', 'category' => 'grossiste', 'contact' => 'Ousmane Diallo', 'phone' => '+225 03 03 03 03 01', 'email' => 'diallo.grossiste@orange.ci'],
        ];

        foreach ($items as $item) {
            Supplier::firstOrCreate(
                ['phone' => $item['phone']],
                [
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'contact_name' => $item['contact'] ?: null,
                    'email' => $item['email'] ?? null,
                    'address' => $item['address'] ?? null,
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('✓ '.Supplier::count().' fournisseurs');
    }

    private function seedClients(): void
    {
        $items = [
            ['name' => 'Fatou N\'Diaye', 'phone' => '+225 01 03 45 67 89', 'gender' => 'Mme'],
            ['name' => 'Mamadou Koné', 'phone' => '+225 07 08 56 78 90', 'gender' => 'M'],
            ['name' => 'Aminata Traoré', 'phone' => '+225 05 04 67 89 01', 'gender' => 'Mme'],
            ['name' => 'Kouamé N\'Guessan', 'phone' => '+225 02 03 78 90 12', 'gender' => 'M'],
            ['name' => 'Awa Coulibaly', 'phone' => '+225 01 01 89 01 23', 'gender' => 'Mme'],
            ['name' => 'Ibrahima Ouattara', 'phone' => '+225 07 07 90 12 34', 'gender' => 'M'],
            ['name' => 'Khady Bamba', 'phone' => '+225 09 08 01 23 45', 'gender' => 'Mme'],
            ['name' => 'Ousmane Sylla', 'phone' => '+225 05 05 12 34 56', 'gender' => 'M'],
            ['name' => 'Mariama Diomandé', 'phone' => '+225 02 02 23 45 67', 'gender' => 'Mme'],
            ['name' => 'Moussa Fofana', 'phone' => '+225 03 04 34 56 78', 'gender' => 'M'],
            ['name' => 'Ndeye Akissi', 'phone' => '+225 01 07 45 67 89', 'gender' => 'Mme'],
            ['name' => 'Abdoulaye Sangaré', 'phone' => '+225 07 02 56 78 90', 'gender' => 'M'],
        ];

        foreach ($items as $item) {
            Client::firstOrCreate(
                ['phone' => $item['phone']],
                [
                    'name' => $item['name'],
                    'gender' => $item['gender'],
                ]
            );
        }

        $this->command?->info('✓ '.Client::count().' clients créés');
    }

    private function seedRecipes(): void
    {
        $recipes = [
            [
                'name' => 'Génoise vanille',
                'category' => 'Biscuit',
                'description' => 'Biscuit génoise léger parfumé à la vanille, idéal pour les entremets et layer cakes.',
                'ingredients' => [
                    ['ingredient' => 'Farine de blé T55', 'qty' => 0.125],
                    ['ingredient' => 'Sucre semoule', 'qty' => 0.125],
                    ['ingredient' => 'Œufs frais calibre M', 'qty' => 4],
                    ['ingredient' => 'Beurre doux 82% MG', 'qty' => 0.030],
                    ['ingredient' => 'Gousse de vanille', 'qty' => 0.5],
                ],
            ],
            [
                'name' => 'Génoise chocolat',
                'category' => 'Biscuit',
                'description' => 'Biscuit génoise au cacao pour entremets chocolatés.',
                'ingredients' => [
                    ['ingredient' => 'Farine de blé T55', 'qty' => 0.100],
                    ['ingredient' => 'Sucre semoule', 'qty' => 0.120],
                    ['ingredient' => 'Œufs frais calibre M', 'qty' => 4],
                    ['ingredient' => 'Cacao en poudre', 'qty' => 0.030],
                    ['ingredient' => 'Beurre doux 82% MG', 'qty' => 0.025],
                ],
            ],
            [
                'name' => 'Crème au beurre',
                'category' => 'Crème',
                'description' => 'Crème onctueuse à base de beurre et de jaunes d\'œufs.',
                'ingredients' => [
                    ['ingredient' => 'Beurre doux 82% MG', 'qty' => 0.250],
                    ['ingredient' => 'Sucre semoule', 'qty' => 0.100],
                    ['ingredient' => 'Œufs frais calibre M', 'qty' => 3],
                    ['ingredient' => 'Gousse de vanille', 'qty' => 0.5],
                ],
            ],
            [
                'name' => 'Ganache chocolat noir',
                'category' => 'Glaçage',
                'description' => 'Ganache onctueuse au chocolat noir pour garniture et nappage.',
                'ingredients' => [
                    ['ingredient' => 'Chocolat noir 70%', 'qty' => 0.200],
                    ['ingredient' => 'Crème liquide entière 35% MG', 'qty' => 0.200],
                    ['ingredient' => 'Beurre doux 82% MG', 'qty' => 0.030],
                    ['ingredient' => 'Sucre semoule', 'qty' => 0.020],
                ],
            ],
            [
                'name' => 'Insert framboise',
                'category' => 'Garniture',
                'description' => 'Insert gelifié à la purée de framboise pour entremets.',
                'ingredients' => [
                    ['ingredient' => 'Purée de framboise', 'qty' => 0.200],
                    ['ingredient' => 'Sucre semoule', 'qty' => 0.040],
                    ['ingredient' => 'Gélatine en feuilles', 'qty' => 4],
                ],
            ],
            [
                'name' => 'Caramel beurre salé',
                'category' => 'Garniture',
                'description' => 'Caramel onctueux au beurre salé pour garniture et nappage.',
                'ingredients' => [
                    ['ingredient' => 'Sucre semoule', 'qty' => 0.200],
                    ['ingredient' => 'Crème liquide entière 35% MG', 'qty' => 0.150],
                    ['ingredient' => 'Beurre doux 82% MG', 'qty' => 0.060],
                    ['ingredient' => 'Sel fin', 'qty' => 0.003],
                ],
            ],
            [
                'name' => 'Pâte à sucre',
                'category' => 'Décoration',
                'description' => 'Pâte de sucre pour couvrir et décorer les gâteaux.',
                'ingredients' => [
                    ['ingredient' => 'Sucre glace', 'qty' => 0.500],
                    ['ingredient' => 'Gélatine en feuilles', 'qty' => 6],
                    ['ingredient' => 'Beurre de cacao', 'qty' => 0.020],
                    ['ingredient' => 'Colorant alimentaire gel', 'qty' => 0.5],
                ],
            ],
            [
                'name' => "Sirop d'imbibage vanille",
                'category' => 'Sirop',
                'description' => 'Sirop parfumé à la vanille pour imbiber les biscuits.',
                'ingredients' => [
                    ['ingredient' => 'Sucre semoule', 'qty' => 0.150],
                    ['ingredient' => 'Gousse de vanille', 'qty' => 1],
                ],
            ],
            [
                'name' => 'Meringue italienne',
                'category' => 'Garniture',
                'description' => 'Meringue à base de blancs d\'œufs montés au sirop chaud.',
                'ingredients' => [
                    ['ingredient' => 'Sucre semoule', 'qty' => 0.200],
                    ['ingredient' => 'Œufs frais calibre M', 'qty' => 4],
                ],
            ],
            [
                'name' => 'Crème mousseline praliné',
                'category' => 'Crème',
                'description' => 'Crème mousseline légère au praliné pour garniture.',
                'ingredients' => [
                    ['ingredient' => 'Lait entier', 'qty' => 0.250],
                    ['ingredient' => 'Œufs frais calibre M', 'qty' => 2],
                    ['ingredient' => 'Sucre semoule', 'qty' => 0.060],
                    ['ingredient' => 'Praliné 50% noisette', 'qty' => 0.100],
                    ['ingredient' => 'Beurre doux 82% MG', 'qty' => 0.150],
                ],
            ],
        ];

        $ingredientMap = Ingredient::pluck('id', 'name');

        foreach ($recipes as $data) {
            $recipe = Recipe::create([
                'name' => $data['name'],
                'category' => $data['category'],
                'description' => $data['description'],
                'is_active' => true,
            ]);

            foreach ($data['ingredients'] as $ri) {
                $ingredientId = $ingredientMap->get($ri['ingredient']);
                if ($ingredientId) {
                    RecipeIngredient::create([
                        'recipe_id' => $recipe->id,
                        'ingredient_id' => $ingredientId,
                        'quantity' => $ri['qty'],

                    ]);
                }
            }
        }

        $this->command?->info('✓ '.Recipe::count().' fiches techniques créées');
    }

    private function seedStockConsumption(): void
    {
        $user = User::first();
        $ingredients = Ingredient::pluck('id', 'name');

        if ($ingredients->isEmpty() || !$user) {
            return;
        }

        $consumptions = [
            ['day' => -6, 'items' => [
                ['name' => 'Farine de blé T55', 'qty' => 2.5],
                ['name' => 'Beurre doux 82% MG', 'qty' => 0.8],
                ['name' => 'Œufs frais calibre M', 'qty' => 18],
                ['name' => 'Sucre semoule', 'qty' => 1.5],
            ]],
            ['day' => -5, 'items' => [
                ['name' => 'Farine de blé T55', 'qty' => 3.0],
                ['name' => 'Chocolat noir 70%', 'qty' => 0.6],
                ['name' => 'Œufs frais calibre M', 'qty' => 24],
                ['name' => 'Crème liquide entière 35% MG', 'qty' => 0.5],
                ['name' => 'Sucre semoule', 'qty' => 2.0],
            ]],
            ['day' => -4, 'items' => [
                ['name' => 'Farine de blé T55', 'qty' => 2.0],
                ['name' => 'Beurre doux 82% MG', 'qty' => 0.5],
                ['name' => 'Lait entier', 'qty' => 1.0],
                ['name' => 'Poudre d\'amande', 'qty' => 0.3],
                ['name' => 'Gousse de vanille', 'qty' => 2],
            ]],
            ['day' => -3, 'items' => [
                ['name' => 'Œufs frais calibre M', 'qty' => 12],
                ['name' => 'Sucre semoule', 'qty' => 1.0],
                ['name' => 'Crème liquide entière 35% MG', 'qty' => 0.3],
                ['name' => 'Praliné 50% noisette', 'qty' => 0.2],
            ]],
            ['day' => -2, 'items' => [
                ['name' => 'Farine de blé T55', 'qty' => 1.5],
                ['name' => 'Beurre doux 82% MG', 'qty' => 0.4],
                ['name' => 'Œufs frais calibre M', 'qty' => 8],
                ['name' => 'Purée de framboise', 'qty' => 0.2],
                ['name' => 'Gélatine en feuilles', 'qty' => 4],
            ]],
            ['day' => -1, 'items' => [
                ['name' => 'Farine de blé T55', 'qty' => 2.0],
                ['name' => 'Sucre semoule', 'qty' => 1.2],
                ['name' => 'Chocolat noir 70%', 'qty' => 0.4],
                ['name' => 'Beurre doux 82% MG', 'qty' => 0.3],
                ['name' => 'Œufs frais calibre M', 'qty' => 16],
            ]],
        ];

        $recorded = 0;

        foreach ($consumptions as $entry) {
            $date = now()->addDays($entry['day'])->setTime(8, 0)->addHours(rand(1, 8));

            foreach ($entry['items'] as $item) {
                $ingredientId = $ingredients->get($item['name']);
                if (!$ingredientId) {
                    continue;
                }

                InventoryMovement::create([
                    'ingredient_id' => $ingredientId,
                    'type' => InventoryMovementType::OUT,
                    'quantity' => $item['qty'],
                    'notes' => 'Consommation du ' . $date->format('d/m/Y'),
                    'user_id' => $user->id,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

                $recorded++;
            }
        }

        $this->command?->info("✓ {$recorded} mouvements de consommation créés");
    }
}
