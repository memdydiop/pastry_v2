<?php

use App\Enums\OrderStatus;
use App\Jobs\CreateOrderStatusLogJob;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('order observer dispatches job when status changes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $order = Order::factory()->create(['status' => OrderStatus::EN_ATTENTE]);

    $order->update(['status' => OrderStatus::CONFIRMÉE]);

    Queue::assertPushed(CreateOrderStatusLogJob::class, function ($job) use ($order, $user) {
        return $job->orderId === $order->id
            && $job->userId === $user->id
            && $job->toStatus === OrderStatus::CONFIRMÉE->value;
    });
});

test('order observer does not dispatch job when status unchanged', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $order = Order::factory()->create(['status' => OrderStatus::CONFIRMÉE]);

    $order->update(['client_name' => 'New Name']);

    Queue::assertNotPushed(CreateOrderStatusLogJob::class);
});

test('order observer does not dispatch job when unauthenticated', function () {
    $order = Order::factory()->create(['status' => OrderStatus::EN_ATTENTE]);

    $order->update(['status' => OrderStatus::CONFIRMÉE]);

    Queue::assertNotPushed(CreateOrderStatusLogJob::class);
});
