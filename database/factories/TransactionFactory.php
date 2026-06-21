<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        static::$sequence++;

        $prefix = static::$sequence % 5 === 0 ? 'Remb' : 'Paiement';
        $datePart = now()->format('Ym');

        return [
            'type' => static::$sequence % 5 === 0 ? TransactionType::REFUND : TransactionType::PAYMENT,
            'order_id' => Order::factory(),
            'amount' => fake()->randomElement([25000, 35000, 50000, 75000, 100000, 150000]),
            'payment_method' => fake()->randomElement(PaymentMethod::cases())->value,
            'paid_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'reference' => $prefix.'-'.$datePart.'-'.str_pad(static::$sequence, 4, '0', STR_PAD_LEFT),
            'external_ref' => fake()->optional(0.3)->bothify('WAVE-########'),
            'notes' => fake()->optional(0.4)->sentence(),
            'user_id' => User::factory(),
        ];
    }
}
