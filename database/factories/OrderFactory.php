<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        static::$sequence++;

        $datePart = now()->format('Ym');

        return [
            'client_id' => Client::factory(),
            'reference' => 'CMD-'.$datePart.'-'.str_pad(static::$sequence, 4, '0', STR_PAD_LEFT),
            'contact_phone_2' => fake()->optional(0.4)->phoneNumber(),
            'contact_phone_3' => fake()->optional(0.2)->phoneNumber(),
            'cake_type' => fake()->randomElement(['Pièce montée', 'Gâteau anniversaire', 'Gâteau baptême', 'Gâteau communion', 'Gâteau mariage', 'Gâteau fête des mères']),
            'tiers_count' => fake()->numberBetween(1, 4),
            'servings_count' => fake()->numberBetween(10, 100),
            'flavors_details' => fake()->randomElement([
                'Génoise vanille, crème mousseline praliné, ganache chocolat noir',
                'Biscuit chocolat, crème diplomate vanille, insert framboise',
                'Génoise amande, crème praliné, insert caramel beurre salé',
                'Moelleux vanille, crème au chocolat, cœur fruits rouges',
                'Biscuit pistache, crème à la rose, framboises fraîches',
            ]),
            'decorations_details' => fake()->randomElement([
                'Fleurs en sucre, ruban satiné perle, étages séparés par piliers',
                'Glaçage miroir, décors en chocolat blanc, bouquet de macarons',
                'Nappage blanc satiné, perles en sucre, croix en chocolat blanc',
                'Pâte à sucre colorée, figurine, bougies étincelles',
                'Glaçage rose nature, pétales de rose cristallisés, ruban de soie',
            ]),
            'theme_description' => fake()->randomElement([
                'Mariage champêtre — tons ivoire et vert sauge',
                'Anniversaire chic — noir et doré',
                'Baptême chrétien — blanc et bleu ciel',
                'Fête des mères — jardin anglais',
                'Mariage traditionnel — champagne et ivoire',
            ]),
            'colors_requested' => fake()->randomElement([
                'Ivoire, vert sauge, blanc cassé',
                'Noir, doré, blanc',
                'Blanc, bleu ciel, argent',
                'Rose ancien, vert tendre, blanc',
                'Champagne, ivoire, caramel',
            ]),
            'inscription_text' => fake()->optional(0.7)->randomElement([
                'Marie & Thomas — 15.06.2026',
                'Joyeux Anniversaire Aminata',
                'Bénédiction de notre fils Nathan',
                'Félicitations Chloé',
                'Maman, je t\'aime',
            ]),
            'delivery_due_at' => fake()->dateTimeBetween('now', '+1 month'),
            'delivery_address' => fake()->optional(0.6)->address(),
            'conservation_notes' => 'À conserver au réfrigérateur jusqu\'à 2h avant la dégustation.',
            'notes' => fake()->optional(0.4)->sentence(),
            'total_amount' => fake()->randomElement([35000, 55000, 75000, 85000, 95000, 120000, 150000, 200000]),
            'status' => fake()->randomElement(OrderStatus::cases())->value,
            'user_id' => User::factory(),
        ];
    }

    public function withTransactions(int $count = 0): static
    {
        $count = $count ?: rand(1, 4);

        return $this->afterCreating(function (Order $order) use ($count) {
            $totalAmount = floatval($order->total_amount);
            $paidSoFar = 0;
            $createdAt = $order->created_at ?? now();
            $deliveryDate = $order->delivery_due_at ?? $createdAt->copy()->addDays(7);

            for ($p = 0; $p < $count; $p++) {
                $isLast = $p === $count - 1;
                $remaining = $totalAmount - $paidSoFar;

                if ($isLast) {
                    $amount = $remaining;
                } else {
                    $maxShare = intval($remaining * 0.7);
                    $amount = round(rand(5000, max(5000, $maxShare)) / 1000) * 1000;
                    $amount = min($amount, $remaining - ($count - $p - 1) * 5000);
                    $amount = max(5000, $amount);
                }

                $dayOffset = intval(($deliveryDate instanceof Carbon ? $deliveryDate->diffInDays($createdAt) : 7) / ($count + 1) * ($p + 1));
                $paymentDate = $createdAt instanceof Carbon ? $createdAt->copy()->addDays(max(0, $dayOffset)) : now();

                $method = fake()->randomElement(PaymentMethod::cases());
                $prefix = $p === 0 ? 'Acompte' : ($isLast ? 'Solde' : 'Versement');

                Transaction::create([
                    'order_id' => $order->id,
                    'type' => 'payment',
                    'reference' => $prefix.'-'.$paymentDate->format('Ym').'-'.str_pad($order->id.$p, 4, '0', STR_PAD_LEFT),
                    'amount' => $amount,
                    'payment_method' => $method->value,
                    'paid_at' => $paymentDate,
                    'external_ref' => in_array($method->value, ['Wave', 'Orange Money', 'Moov Money']) ? fake()->bothify('TXN-########') : null,
                    'notes' => "$prefix de ".number_format($amount, 0, ',', ' ')." FCFA reçu en {$method->label()}",
                    'user_id' => $order->user_id ?? User::factory(),
                    'created_at' => $paymentDate,
                    'updated_at' => $paymentDate,
                ]);

                $paidSoFar += $amount;
            }
        });
    }
}
