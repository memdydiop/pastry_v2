<?php

namespace App\Observers;

use App\Jobs\CreateOrderStatusLogJob;
use App\Models\Order;

/**
 * Observer Eloquent pour le modèle Order.
 *
 * Responsabilité unique : réagir aux changements d'état de la commande
 * sans polluer la méthode boot() du modèle.
 *
 * Enregistrement dans App\Providers\AppServiceProvider::boot() :
 *   Order::observe(OrderObserver::class);
 */
class OrderObserver
{
    public function updated(Order $order): void
    {
        if ($order->wasChanged('status') && auth()->check()) {
            CreateOrderStatusLogJob::dispatch(
                orderId: $order->id,
                fromStatus: $order->getOriginal('status'),
                toStatus: $order->status->value,
                userId: auth()->id(),
            )->afterCommit();
        }
    }
}
