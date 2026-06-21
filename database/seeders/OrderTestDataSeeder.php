<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderLevel;
use App\Models\OrderStatusLog;
use App\Models\Recipe;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $gerant = User::where('email', 'gerant@patisserie.com')->first();
        if (! $gerant) {
            $gerant = User::factory()->create(['name' => 'Responsable Pâtisserie', 'email' => 'gerant@patisserie.com']);
        }

        $users = User::factory(5)->create();
        $allUsers = collect([$gerant, ...$users]);

        $clients = Client::all();

        if ($clients->isEmpty()) {
            $clients = Client::factory(20)->create();
        }

        $recipeMap = Recipe::pluck('id', 'name');

        $cakeConfigs = [
            ['type' => 'Pièce montée', 'tiers' => 3, 'servings' => 50, 'amount' => 150000,
                'flavors' => 'Génoise vanille, crème mousseline praliné, ganache chocolat noir',
                'decorations' => 'Fleurs en sucre, ruban satiné perle, étage séparé par piliers',
                'theme' => 'Mariage champêtre — tons ivoire et vert sauge',
                'colors' => 'Ivoire, vert sauge, blanc cassé',
                'inscription' => 'Marie & Thomas — 15.06.2026',
                'levels' => [
                    ['biscuit' => 'Génoise vanille', 'cream' => 'Mousseline praliné', 'filling' => 'Ganache chocolat', 'allergens' => 'Fruits à coque', 'diameter' => 30, 'height' => 12],
                    ['biscuit' => 'Génoise chocolat', 'cream' => 'Crème au beurre café', 'filling' => 'Praliné noisette', 'allergens' => 'Fruits à coque, lactose', 'diameter' => 22, 'height' => 10],
                    ['biscuit' => 'Dacquoise noisette', 'cream' => 'Ganache montée', 'filling' => 'Confiture framboise', 'allergens' => 'Fruits à coque', 'diameter' => 15, 'height' => 8],
                ]],
            ['type' => 'Gâteau anniversaire', 'tiers' => 2, 'servings' => 25, 'amount' => 75000,
                'flavors' => 'Biscuit chocolat, crème diplomate vanille, insert framboise',
                'decorations' => 'Glaçage miroir rose, décors en chocolat blanc, bouquet de macarons',
                'theme' => 'Anniversaire 30 ans — élégant chic',
                'colors' => 'Blanc, rose poudré, or rose',
                'inscription' => 'Joyeux Anniversaire Aminata',
                'levels' => [
                    ['biscuit' => 'Moelleux chocolat', 'cream' => 'Diplomate vanille', 'filling' => 'Insert framboise', 'allergens' => 'Lactose, gluten', 'diameter' => 24, 'height' => 10],
                    ['biscuit' => 'Biscuit joconde', 'cream' => 'Crème au beurre', 'filling' => 'Confiture fraise', 'allergens' => '', 'diameter' => 16, 'height' => 8],
                ]],
            ['type' => 'Gâteau baptême', 'tiers' => 2, 'servings' => 30, 'amount' => 85000,
                'flavors' => 'Génoise vanille, crème pâtissière, fruits exotiques',
                'decorations' => 'Nappage blanc satiné, perles en sucre, croix en chocolat blanc',
                'theme' => 'Baptême chrétien — blanc et bleu ciel',
                'colors' => 'Blanc, bleu ciel, argent',
                'inscription' => 'Bénédiction de notre fils Nathan',
                'levels' => [
                    ['biscuit' => 'Génoise vanille', 'cream' => 'Pâtissière', 'filling' => 'Mangue-passion', 'allergens' => '', 'diameter' => 26, 'height' => 10],
                    ['biscuit' => 'Génoise amande', 'cream' => 'Crème légère', 'filling' => 'Fruits de la passion', 'allergens' => 'Fruits à coque', 'diameter' => 18, 'height' => 8],
                ]],
            ['type' => 'Gâteau communion', 'tiers' => 1, 'servings' => 20, 'amount' => 55000,
                'flavors' => 'Biscuit moelleux vanille, ganache blanche, cœur framboise',
                'decorations' => 'Glaçage blanc mat, croix en pâte à sucre, fleurs en dentelle',
                'theme' => 'Première communion',
                'colors' => 'Blanc, or, rose pâle',
                'inscription' => 'Félicitations Chloé',
                'levels' => [
                    ['biscuit' => 'Moelleux vanille', 'cream' => 'Ganache blanche', 'filling' => 'Cœur framboise', 'allergens' => 'Lactose', 'diameter' => 24, 'height' => 12],
                ]],
            ['type' => 'Pièce montée', 'tiers' => 4, 'servings' => 70, 'amount' => 200000,
                'flavors' => 'Génoise amande, crème praliné, insert caramel beurre salé',
                'decorations' => 'Croûte de caramel, éclats de praline, roses en pâte à sucre',
                'theme' => 'Mariage traditionnel — tons champagne et ivoire',
                'colors' => 'Champagne, ivoire, caramel',
                'inscription' => 'Awa & Oumar — 20.07.2026',
                'levels' => [
                    ['biscuit' => 'Génoise amande', 'cream' => 'Praliné', 'filling' => 'Caramel beurre salé', 'allergens' => 'Fruits à coque', 'diameter' => 32, 'height' => 14],
                    ['biscuit' => 'Biscuit noisette', 'cream' => 'Crème praliné', 'filling' => 'Praliné feuilleté', 'allergens' => 'Fruits à coque, gluten', 'diameter' => 24, 'height' => 10],
                    ['biscuit' => 'Dacquoise', 'cream' => 'Mousseline', 'filling' => 'Confiture abricot', 'allergens' => 'Fruits à coque', 'diameter' => 18, 'height' => 8],
                    ['biscuit' => 'Biscuit cuillère', 'cream' => 'Crème légère', 'filling' => 'Framboise', 'allergens' => '', 'diameter' => 12, 'height' => 6],
                ]],
            ['type' => 'Gâteau d\'anniversaire enfant', 'tiers' => 1, 'servings' => 15, 'amount' => 35000,
                'flavors' => 'Moelleux vanille, crème au chocolat, smarties',
                'decorations' => 'Pâte à sucre colorée, figurine Pokémon, bougies étincelles',
                'theme' => 'Pokémon — 7 ans',
                'colors' => 'Rouge, jaune, noir',
                'inscription' => 'Bon anniversaire Léo !',
                'levels' => [
                    ['biscuit' => 'Moelleux vanille', 'cream' => 'Crème au chocolat', 'filling' => 'Smarties', 'allergens' => '', 'diameter' => 20, 'height' => 10],
                ]],
            ['type' => 'Gâteau fête des mères', 'tiers' => 1, 'servings' => 12, 'amount' => 30000,
                'flavors' => 'Biscuit pistache, crème à la rose, framboises fraîches',
                'decorations' => 'Glaçage rose nature, pétales de rose cristallisés, ruban de soie',
                'theme' => 'Fête des mères — jardin anglais',
                'colors' => 'Rose ancien, vert tendre, blanc',
                'inscription' => 'Maman, je t\'aime',
                'levels' => [
                    ['biscuit' => 'Biscuit pistache', 'cream' => 'Crème à la rose', 'filling' => 'Framboises', 'allergens' => 'Fruits à coque', 'diameter' => 18, 'height' => 10],
                ]],
            ['type' => 'Gâteau de fiançailles', 'tiers' => 2, 'servings' => 35, 'amount' => 95000,
                'flavors' => 'Génoise vanille, crème diplomate, insert fruits rouges',
                'decorations' => 'Nappage blush, anneaux en sucre, fleurs fraîches stabilisées',
                'theme' => 'Fiançailles bohème',
                'colors' => 'Blush, nude, blanc',
                'inscription' => 'Sarah & Karim — 08.2026',
                'levels' => [
                    ['biscuit' => 'Génoise vanille', 'cream' => 'Diplomate', 'filling' => 'Fruits rouges', 'allergens' => '', 'diameter' => 26, 'height' => 12],
                    ['biscuit' => 'Biscuit amande', 'cream' => 'Crème légère', 'filling' => 'Confiture groseille', 'allergens' => 'Fruits à coque', 'diameter' => 18, 'height' => 8],
                ]],
            ['type' => 'Gâteau d\'anniversaire', 'tiers' => 2, 'servings' => 25, 'amount' => 65000,
                'flavors' => 'Biscuit chocolat-noisette, ganache montée caramel',
                'decorations' => 'Glaçage miroir chocolat, copeaux, macarons chocolat',
                'theme' => 'Anniversaire 50 ans — sobre et chic',
                'colors' => 'Marron, or, crème',
                'inscription' => '50 ans — Bon anniversaire Papa',
                'levels' => [
                    ['biscuit' => 'Génoise noisette', 'cream' => 'Ganache montée caramel', 'filling' => 'Caramel beurre salé', 'allergens' => 'Fruits à coque', 'diameter' => 24, 'height' => 10],
                    ['biscuit' => 'Biscuit chocolat', 'cream' => 'Crème praliné', 'filling' => 'Praliné feuilleté', 'allergens' => 'Fruits à coque', 'diameter' => 16, 'height' => 8],
                ]],
            ['type' => 'Gâteau de mariage', 'tiers' => 3, 'servings' => 60, 'amount' => 180000,
                'flavors' => 'Génoise vanille, crème légère à la framboise, insert citron',
                'decorations' => 'Fleurs fraîches, perles argent, nappage blanc satin',
                'theme' => 'Mariage romantique — blanc et argent',
                'colors' => 'Blanc pur, argent, rose pâle',
                'inscription' => 'Élodie & Alexandre — 12.09.2026',
                'levels' => [
                    ['biscuit' => 'Génoise vanille', 'cream' => 'Crème légère framboise', 'filling' => 'Insert citron', 'allergens' => '', 'diameter' => 28, 'height' => 12],
                    ['biscuit' => 'Biscuit amande', 'cream' => 'Mousseline vanille', 'filling' => 'Confiture fraise', 'allergens' => 'Fruits à coque', 'diameter' => 20, 'height' => 10],
                    ['biscuit' => 'Dacquoise noisette', 'cream' => 'Ganache blanche', 'filling' => 'Framboise', 'allergens' => 'Fruits à coque', 'diameter' => 14, 'height' => 8],
                ]],
        ];

        $statuses = OrderStatus::cases();
        $paymentMethods = PaymentMethod::cases();

        $txCounter = Transaction::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $existingSequences = Order::where('reference', 'like', 'CMD-%%%%-____')
            ->pluck('reference')
            ->map(fn ($ref) => intval(substr($ref, -4)))
            ->max() ?? 0;

        $globalIndex = 0;

        foreach (range(0, 29) as $dayOffset) {
            $dayDate = now()->subDays(29 - $dayOffset);
            $ordersToday = rand(2, 5);

            for ($o = 0; $o < $ordersToday; $o++) {
                $config = $cakeConfigs[array_rand($cakeConfigs)];
                $client = $clients->random();
                $user = $allUsers->random();

                $createdAt = $dayDate->copy()->addMinutes(rand(0, 1439));
                $deliveryDate = $createdAt->copy()->addDays(rand(3, 21));

                $globalIndex++;
                $sequence = $existingSequences + $globalIndex;
                $reference = 'CMD-'.$createdAt->format('Ym').'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);

                $statusRoll = rand(1, 100);
                $status = match (true) {
                    $statusRoll <= 40 => OrderStatus::LIVRÉE,
                    $statusRoll <= 50 => OrderStatus::EN_LIVRAISON,
                    $statusRoll <= 60 => OrderStatus::PRÊTE,
                    $statusRoll <= 68 => OrderStatus::EN_PRODUCTION,
                    $statusRoll <= 75 => OrderStatus::CONFIRMÉE,
                    $statusRoll <= 82 => OrderStatus::ACOMPTE_PERÇU,
                    $statusRoll <= 92 => OrderStatus::EN_ATTENTE,
                    default => OrderStatus::ANNULÉE,
                };

                $totalAmount = $config['amount'];

                $deliveryAddress = fake()->optional(0.6)->address();
                $notes = fake()->optional(0.3)->sentence();

                $orderData = [
                    'reference' => $reference,
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'client_phone' => $client->phone,
                    'cake_type' => $config['type'],
                    'tiers_count' => $config['tiers'],
                    'servings_count' => $config['servings'],
                    'flavors_details' => $config['flavors'],
                    'decorations_details' => $config['decorations'],
                    'theme_description' => $config['theme'],
                    'colors_requested' => $config['colors'],
                    'inscription_text' => $config['inscription'],
                    'delivery_due_at' => $deliveryDate->format('Y-m-d H:i:s'),
                    'delivery_address' => $deliveryAddress,
                    'conservation_notes' => 'À conserver au réfrigérateur jusqu\'à 2h avant la dégustation. Ne pas exposer au soleil.',
                    'allergens' => collect($config['levels'])->pluck('allergens')->filter()->unique()->implode(', '),
                    'notes' => $notes,
                    'total_amount' => $config['amount'],
                    'status' => $status->value,
                    'user_id' => $user->id,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                if ($status === OrderStatus::ANNULÉE) {
                    $orderData['cancelled_at'] = $createdAt->copy()->addDays(rand(1, 3));
                    $orderData['cancelled_by'] = $gerant->id;
                    $orderData['cancellation_reason'] = 'Annulation client ou impératif technique';
                }

                $order = Order::create($orderData);

                foreach ($config['levels'] as $idx => $levelData) {
                    $recipeId = $recipeMap->get($levelData['biscuit']) ?? null;

                    OrderLevel::create([
                        'order_id' => $order->id,
                        'recipe_id' => $recipeId,
                        'level_number' => $idx + 1,
                        'flavor_biscuit' => $levelData['biscuit'],
                        'flavor_cream' => $levelData['cream'],
                        'filling' => $levelData['filling'],
                        'diameter_cm' => $levelData['diameter'],
                        'height_cm' => $levelData['height'],
                    ]);
                }

                $statusIndex = array_search($status, $statuses);
                if ($statusIndex !== false) {
                    $logDate = $createdAt->copy();
                    OrderStatusLog::create([
                        'order_id' => $order->id,
                        'from_status' => null,
                        'to_status' => $statuses[0],
                        'user_id' => $user->id,
                        'created_at' => $logDate,
                    ]);

                    for ($s = 0; $s < $statusIndex; $s++) {
                        $logDate = $createdAt->copy()->addDays($s * 2 + rand(0, 1));
                        OrderStatusLog::create([
                            'order_id' => $order->id,
                            'from_status' => $statuses[$s],
                            'to_status' => $statuses[$s + 1],
                            'user_id' => $allUsers->random()->id,
                            'created_at' => $logDate,
                        ]);
                    }
                }

                if ($status === OrderStatus::ANNULÉE) {
                    $paymentCount = rand(1, 2);
                } else {
                    $paymentCount = match ($status) {
                        OrderStatus::LIVRÉE => rand(3, 5),
                        OrderStatus::EN_LIVRAISON, OrderStatus::PRÊTE => rand(2, 4),
                        OrderStatus::EN_PRODUCTION => rand(2, 3),
                        OrderStatus::CONFIRMÉE => rand(1, 2),
                        OrderStatus::ACOMPTE_PERÇU => rand(1, 2),
                        OrderStatus::EN_ATTENTE => rand(0, 1),
                        default => rand(1, 3),
                    };
                }

                $paymentsTotal = 0;
                $lastPaymentTx = null;

                for ($p = 0; $p < $paymentCount; $p++) {
                    $isLast = $p === $paymentCount - 1;
                    $remainingAmount = $totalAmount - $paymentsTotal;

                    if ($remainingAmount <= 0) {
                        break;
                    }

                    if ($isLast && $status === OrderStatus::LIVRÉE) {
                        $paymentAmount = $remainingAmount;
                    } elseif ($isLast) {
                        $maxShare = intval($remainingAmount * 0.8);
                        $minVal = max(5000, intval($remainingAmount * 0.3));
                        $maxVal = max($minVal, $maxShare);
                        $paymentAmount = round(rand($minVal, $maxVal) / 1000) * 1000;
                        $paymentAmount = max(5000, min($paymentAmount, $remainingAmount - 5000));
                    } else {
                        $maxShare = intval($remainingAmount * 0.7);
                        $minVal = max(5000, intval($remainingAmount * 0.15));
                        $maxVal = max($minVal, $maxShare);
                        $paymentAmount = round(rand($minVal, $maxVal) / 1000) * 1000;
                        $paymentAmount = min($paymentAmount, $remainingAmount - ($paymentCount - $p - 1) * 5000);
                        $paymentAmount = max(5000, $paymentAmount);
                    }

                    $dayOffset = intval(($deliveryDate->diffInDays($createdAt) + 1) / ($paymentCount + 1) * ($p + 1));
                    $paymentDate = $createdAt->copy()->addDays($dayOffset)->addHours(rand(8, 18));

                    $method = $paymentMethods[array_rand($paymentMethods)];
                    $txCounter++;
                    $lastPaymentTx = Transaction::create([
                        'order_id' => $order->id,
                        'type' => TransactionType::PAYMENT,
                        'reference' => 'Paiement-'.$paymentDate->format('Ym').'-'.str_pad($txCounter, 4, '0', STR_PAD_LEFT),
                        'amount' => $paymentAmount,
                        'payment_method' => $method->value,
                        'paid_at' => $paymentDate,
                        'external_ref' => in_array($method->value, ['Wave', 'Orange Money', 'Moov Money']) ? fake()->bothify('TXN-########') : null,
                        'notes' => ($p === 0 ? 'Acompte' : ($isLast ? 'Solde' : 'Versement')).' de '.number_format($paymentAmount, 0, ',', ' ')." FCFA reçu en {$method->label()}",
                        'user_id' => $allUsers->random()->id,
                        'created_at' => $paymentDate,
                        'updated_at' => $paymentDate,
                    ]);

                    $paymentsTotal += $paymentAmount;
                }

                $shouldRefund = match ($status) {
                    OrderStatus::ANNULÉE => $paymentsTotal > 0,
                    OrderStatus::LIVRÉE => rand(1, 100) <= 15 && $paymentsTotal > 0,
                    default => false,
                };

                if ($shouldRefund) {
                    $refundDate = $status === OrderStatus::ANNULÉE
                        ? $order->cancelled_at->copy()->addHours(rand(1, 24))
                        : $deliveryDate->copy()->addDays(rand(1, 5))->addHours(rand(8, 18));

                    $refundAmount = $status === OrderStatus::ANNULÉE
                        ? $paymentsTotal
                        : round($paymentsTotal * rand(10, 30) / 100 / 1000) * 1000;

                    $refundAmount = max(1000, $refundAmount);

                    $txCounter++;
                    Transaction::create([
                        'order_id' => $order->id,
                        'type' => TransactionType::REFUND,
                        'parent_transaction_id' => $lastPaymentTx?->id,
                        'reference' => 'Remb-'.$refundDate->format('Ym').'-'.str_pad($txCounter, 4, '0', STR_PAD_LEFT),
                        'amount' => $refundAmount,
                        'payment_method' => PaymentMethod::ESPÈCES->value,
                        'paid_at' => $refundDate,
                        'notes' => $status === OrderStatus::ANNULÉE
                            ? "Remboursement total de {$refundAmount} FCFA — commande annulée {$order->reference}"
                            : "Remboursement partiel de {$refundAmount} FCFA sur la commande {$order->reference}",
                        'user_id' => $gerant->id,
                        'created_at' => $refundDate,
                        'updated_at' => $refundDate,
                    ]);
                }
            }
        }

        $this->command?->info('Données de test créées : '.Client::count().' clients, '.Order::count().' commandes, '.Transaction::count().' transactions');
    }
}
