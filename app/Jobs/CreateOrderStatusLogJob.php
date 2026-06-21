<?php

namespace App\Jobs;

use App\Models\OrderStatusLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Crée un enregistrement de changement de statut de commande en arrière-plan.
 * Évite de bloquer la réponse Livewire lors d'une mise à jour de statut.
 */
class CreateOrderStatusLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly mixed $fromStatus,
        public readonly string $toStatus,
        public readonly int $userId,
    ) {}

    public function handle(): void
    {
        OrderStatusLog::create([
            'order_id' => $this->orderId,
            'from_status' => $this->fromStatus instanceof \BackedEnum
                ? $this->fromStatus->value
                : $this->fromStatus,
            'to_status' => $this->toStatus,
            'user_id' => $this->userId,
        ]);
    }
}
