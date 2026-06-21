<?php

use App\Enums\OrderStatus;
use App\Jobs\CreateOrderStatusLogJob;
use App\Models\Order;
use App\Models\User;

test('create order status log job creates log entry', function () {
    $order = Order::factory()->create(['status' => OrderStatus::CONFIRMÉE]);
    $user = User::factory()->create();

    $job = new CreateOrderStatusLogJob(
        orderId: $order->id,
        fromStatus: OrderStatus::EN_ATTENTE,
        toStatus: OrderStatus::CONFIRMÉE->value,
        userId: $user->id,
    );

    $job->handle();

    $this->assertDatabaseHas('order_status_logs', [
        'order_id' => $order->id,
        'from_status' => OrderStatus::EN_ATTENTE->value,
        'to_status' => OrderStatus::CONFIRMÉE->value,
        'user_id' => $user->id,
    ]);
});

test('create order status log job handles string from status', function () {
    $order = Order::factory()->create(['status' => OrderStatus::LIVRÉE]);
    $user = User::factory()->create();

    $job = new CreateOrderStatusLogJob(
        orderId: $order->id,
        fromStatus: 'en_attente',
        toStatus: OrderStatus::LIVRÉE->value,
        userId: $user->id,
    );

    $job->handle();

    $this->assertDatabaseHas('order_status_logs', [
        'order_id' => $order->id,
        'from_status' => 'en_attente',
    ]);
});
