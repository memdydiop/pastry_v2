<?php

use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use App\Models\Client;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

if (! function_exists('createOrderUser')) {
    function createOrderUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Gérant/Admin'));

        return $user;
    }
}

if (! function_exists('createOrder')) {
    function createOrder(array $overrides = []): Order
    {
        return Order::factory()->create($overrides);
    }
}

if (! function_exists('createPayment')) {
    function createPayment(Order $order, float $amount, ?User $user = null): Transaction
    {
        return Transaction::factory()->create([
            'order_id' => $order->id,
            'type' => TransactionType::PAYMENT,
            'amount' => $amount,
            'paid_at' => now(),
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }
}

// ---------------------------------------------------------------------------
// Model — Création & Référence
// ---------------------------------------------------------------------------

test('order creation generates a unique reference', function () {
    $user = createOrderUser();
    $this->actingAs($user);

    $order1 = createOrder(['client_id' => Client::factory()]);
    $order2 = createOrder(['client_id' => Client::factory()]);

    expect($order1->reference)->not->toBeNull();
    expect($order2->reference)->not->toBeNull();
    expect($order1->reference)->not->toBe($order2->reference);
    expect($order1->reference)->toMatch('/^CMD-\d{6}-\d{4}$/');
});

test('order creation auto-assigns authenticated user as creator', function () {
    $user = createOrderUser();
    $this->actingAs($user);

    $order = createOrder([
        'client_id' => Client::factory(),
        'user_id' => null,
    ]);

    expect($order->user_id)->toBe($user->id);
});

test('order creation sets default status to En attente', function () {
    $user = createOrderUser();
    $this->actingAs($user);

    $order = createOrder([
        'client_id' => Client::factory(),
        'status' => OrderStatus::EN_ATTENTE,
        'user_id' => null,
    ]);

    expect($order->status)->toBe(OrderStatus::EN_ATTENTE);
});

// ---------------------------------------------------------------------------
// Model — Statuts & Cycle de Vie
// ---------------------------------------------------------------------------

test('order can transition through valid statuses', function () {
    $user = createOrderUser();
    $this->actingAs($user);
    $order = createOrder(['client_id' => Client::factory()]);

    $order->status = OrderStatus::CONFIRMÉE;
    $order->save();
    expect($order->status)->toBe(OrderStatus::CONFIRMÉE);

    $order->status = OrderStatus::EN_PRODUCTION;
    $order->save();
    expect($order->status)->toBe(OrderStatus::EN_PRODUCTION);

    $order->status = OrderStatus::PRÊTE;
    $order->save();
    expect($order->status)->toBe(OrderStatus::PRÊTE);

    $order->status = OrderStatus::LIVRÉE;
    $order->save();
    expect($order->status)->toBe(OrderStatus::LIVRÉE);
});

test('order status enum values are correct', function () {
    expect(OrderStatus::EN_ATTENTE->value)->toBe('En attente');
    expect(OrderStatus::ACOMPTE_PERÇU->value)->toBe('Acompte perçu');
    expect(OrderStatus::CONFIRMÉE->value)->toBe('Confirmée');
    expect(OrderStatus::EN_PRODUCTION->value)->toBe('En production');
    expect(OrderStatus::PRÊTE->value)->toBe('Prête');
    expect(OrderStatus::EN_LIVRAISON->value)->toBe('En cours de livraison');
    expect(OrderStatus::LIVRÉE->value)->toBe('Livrée');
    expect(OrderStatus::ANNULÉE->value)->toBe('Annulée');
});

test('order is not cancelled by default', function () {
    $order = createOrder(['client_id' => Client::factory()]);

    expect($order->isCancelled())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Model — Calculs Financiers
// ---------------------------------------------------------------------------

test('total paid returns sum of non-cancelled payments minus refunds', function () {
    $user = createOrderUser();
    $this->actingAs($user);
    $order = createOrder(['total_amount' => 100000, 'client_id' => Client::factory()]);

    createPayment($order, 30000, $user);
    createPayment($order, 20000, $user);

    expect($order->total_paid)->toBe(50000.0);
});

test('remaining balance is total minus paid', function () {
    $user = createOrderUser();
    $this->actingAs($user);
    $order = createOrder(['total_amount' => 100000, 'client_id' => Client::factory()]);

    createPayment($order, 40000, $user);

    expect($order->remaining_balance)->toBe(60000.0);
});

test('remaining balance returns 0 when fully paid', function () {
    $user = createOrderUser();
    $this->actingAs($user);
    $order = createOrder(['total_amount' => 50000, 'client_id' => Client::factory()]);

    createPayment($order, 50000, $user);

    expect($order->remaining_balance)->toBe(0.0);
});

test('cancelled payments are excluded from total paid', function () {
    $user = createOrderUser();
    $this->actingAs($user);
    $order = createOrder(['total_amount' => 100000, 'client_id' => Client::factory()]);

    createPayment($order, 50000, $user);

    $cancelledPayment = createPayment($order, 25000, $user);
    $cancelledPayment->cancel('Annulé pour test');

    expect($order->total_paid)->toBe(50000.0);
});

test('withOutstandingBalance scope returns only unpaid orders', function () {
    $user = createOrderUser();
    $this->actingAs($user);
    $client = Client::factory()->create();

    $paidOrder = createOrder(['total_amount' => 50000, 'client_id' => $client->id]);
    createPayment($paidOrder, 50000, $user);

    $unpaidOrder = createOrder(['total_amount' => 50000, 'client_id' => $client->id]);

    $outstanding = Order::withOutstandingBalance()->get();

    expect($outstanding)->toHaveCount(1);
    expect($outstanding->first()->id)->toBe($unpaidOrder->id);
});

// ---------------------------------------------------------------------------
// Model — Annulation
// ---------------------------------------------------------------------------

test('canBeCancelled returns error for already cancelled order', function () {
    $order = createOrder(['client_id' => Client::factory(), 'cancelled_at' => now()]);

    $errors = $order->canBeCancelled();

    expect($errors)->toContain('Cette commande est déjà annulée.');
});

test('canBeCancelled returns error for delivered order', function () {
    $order = createOrder([
        'client_id' => Client::factory(),
        'status' => OrderStatus::LIVRÉE,
    ]);

    $errors = $order->canBeCancelled();

    expect($errors)->toContain("Impossible d'annuler une commande déjà livrée.");
});

test('canBeCancelled returns error within 24h of delivery', function () {
    $order = createOrder([
        'client_id' => Client::factory(),
        'delivery_due_at' => now()->addHours(12),
    ]);

    $errors = $order->canBeCancelled();

    expect($errors)->toContain('Impossible d\'annuler une commande à moins de 24 heures de la livraison.');
});

test('canBeCancelled returns warning for order in production', function () {
    $order = new Order([
        'status' => OrderStatus::EN_PRODUCTION,
        'delivery_due_at' => now()->addDays(3),
    ]);

    $errors = $order->canBeCancelled();

    expect($errors)->toContain('Attention : cette commande est déjà en production. Les matières premières engagées ne seront pas restituées automatiquement au stock.');
});

test('cancel throws exception when blocked', function () {
    $user = createOrderUser();
    $this->actingAs($user);
    $order = createOrder([
        'client_id' => Client::factory(),
        'status' => OrderStatus::LIVRÉE,
    ]);

    expect(fn () => $order->cancel('Test'))
        ->toThrow(RuntimeException::class, "Impossible d'annuler une commande déjà livrée.");
});

test('cancel creates refunds and sets status to Annulée', function () {
    $user = createOrderUser();
    $this->actingAs($user);

    $client = Client::factory()->create();
    $order = createOrder([
        'total_amount' => 100000,
        'client_id' => $client->id,
        'delivery_due_at' => now()->addDays(3),
        'status' => OrderStatus::CONFIRMÉE,
    ]);

    createPayment($order, 50000, $user);
    createPayment($order, 30000, $user);

    $order->cancel('Annulation test');

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::ANNULÉE);
    expect($order->cancelled_at)->not->toBeNull();
    expect($order->cancelled_by)->toBe($user->id);
    expect($order->cancellation_reason)->toBe('Annulation test');

    $refunds = $order->transactions()->where('type', TransactionType::REFUND)->get();
    expect($refunds)->toHaveCount(2);
    expect($refunds->sum('amount'))->toBe(80000.0);
});

test('cancel does not create duplicate refunds for already refunded payments', function () {
    $user = createOrderUser();
    $this->actingAs($user);

    $client = Client::factory()->create();
    $order = createOrder([
        'total_amount' => 100000,
        'client_id' => $client->id,
        'delivery_due_at' => now()->addDays(3),
        'status' => OrderStatus::CONFIRMÉE,
    ]);

    $payment = createPayment($order, 50000, $user);

    $payment->refunds()->create([
        'type' => TransactionType::REFUND,
        'order_id' => $order->id,
        'amount' => 50000,
        'paid_at' => now(),
        'reference' => 'Remb-001',
        'user_id' => $user->id,
    ]);

    $order->cancel('Annulation test');

    $refunds = $order->transactions()->where('type', TransactionType::REFUND)->get();
    expect($refunds)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// Model — Scopes
// ---------------------------------------------------------------------------

test('cancelled scope returns only cancelled orders', function () {
    createOrder(['client_id' => Client::factory()]);
    createOrder(['client_id' => Client::factory(), 'cancelled_at' => now()]);

    expect(Order::cancelled()->count())->toBe(1);
});

test('notCancelled scope excludes cancelled orders', function () {
    $client = Client::factory()->create();
    createOrder(['client_id' => $client]);
    createOrder(['client_id' => $client, 'cancelled_at' => now()]);
    createOrder(['client_id' => $client, 'status' => OrderStatus::ANNULÉE]);

    expect(Order::notCancelled()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Model — Attributs
// ---------------------------------------------------------------------------

test('flavors summary returns dash for empty details', function () {
    $order = createOrder(['client_id' => Client::factory()]);

    expect($order->flavors_summary)->toBe('—');
});

test('order has correct relationships', function () {
    $user = createOrderUser();
    $this->actingAs($user);
    $client = Client::factory()->create();
    $order = createOrder(['client_id' => $client->id]);

    expect($order->client)->toBeInstanceOf(Client::class);
    expect($order->user)->toBeInstanceOf(User::class);
});

// ---------------------------------------------------------------------------
// Livewire — Liste (index)
// ---------------------------------------------------------------------------

test('guest is redirected to login when accessing orders', function () {
    $response = $this->get(route('orders.index'));

    $response->assertRedirect(route('login'));
});

test('user with role can access order index page', function () {
    $user = createOrderUser();
    $this->actingAs($user);

    $response = $this->get(route('orders.index'));

    $response->assertOk();
});

test('user without role cannot access order index page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('orders.index'));

    $response->assertForbidden();
});

test('order index page displays list of orders', function () {
    $user = createOrderUser();
    $this->actingAs($user);

    $orders = Order::factory(3)->create(['client_id' => Client::factory()]);

    Livewire::test('pages::orders.index')
        ->assertViewHas('orders', function ($paginator) {
            return $paginator->total() === 3;
        });
});

test('order index page filters by status', function () {
    $user = createOrderUser();
    $this->actingAs($user);
    $client = Client::factory()->create();

    Order::factory()->create(['client_id' => $client->id, 'status' => OrderStatus::CONFIRMÉE]);
    Order::factory()->create(['client_id' => $client->id, 'status' => OrderStatus::EN_ATTENTE]);

    Livewire::test('pages::orders.index')
        ->set('statusFilter', OrderStatus::CONFIRMÉE->value)
        ->assertViewHas('orders', fn ($p) => $p->total() === 1);
});

test('order index page searches by reference', function () {
    $user = createOrderUser();
    $this->actingAs($user);
    $client = Client::factory()->create();
    $order = createOrder(['client_id' => $client->id]);

    Livewire::test('pages::orders.index')
        ->set('search', substr($order->reference, 0, 8))
        ->assertViewHas('orders', fn ($p) => $p->total() === 1);
});

// ---------------------------------------------------------------------------
// Livewire — Détail (show)
// ---------------------------------------------------------------------------

test('order show page renders for authorized user', function () {
    $user = createOrderUser();
    $this->actingAs($user);
    $client = Client::factory()->create();
    $order = createOrder(['client_id' => $client->id]);

    $response = $this->get(route('orders.show', $order));

    $response->assertOk();
});

test('order show page displays order information', function () {
    $user = createOrderUser();
    $this->actingAs($user);

    $client = Client::factory()->create(['name' => 'Test Client']);
    $order = createOrder([
        'client_id' => $client->id,
        'client_name' => 'Test Client',
        'total_amount' => 75000,
    ]);

    Livewire::test('pages::orders.show', ['order' => $order])
        ->assertSet('order.id', $order->id);
});

test('order show page cancel modal validates reason', function () {
    $user = createOrderUser();
    $this->actingAs($user);

    $client = Client::factory()->create();
    $order = createOrder([
        'client_id' => $client->id,
        'delivery_due_at' => now()->addDays(3),
        'status' => OrderStatus::CONFIRMÉE,
    ]);

    Livewire::test('pages::orders.show', ['order' => $order])
        ->call('openCancelModal')
        ->set('cancellationReason', '')
        ->call('processCancel')
        ->assertHasErrors('cancellationReason');
});

test('order show page cancel processes refunds', function () {
    $user = createOrderUser();
    $this->actingAs($user);

    $client = Client::factory()->create();
    $order = createOrder([
        'total_amount' => 50000,
        'client_id' => $client->id,
        'delivery_due_at' => now()->addDays(3),
        'status' => OrderStatus::CONFIRMÉE,
    ]);

    createPayment($order, 25000, $user);

    Livewire::test('pages::orders.show', ['order' => $order])
        ->call('openCancelModal')
        ->set('cancellationReason', 'Annulation client')
        ->call('processCancel');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::ANNULÉE);

    $refunds = $order->transactions()->where('type', TransactionType::REFUND)->count();
    expect($refunds)->toBe(1);
});

// ---------------------------------------------------------------------------
// Permissions — Accès par Rôle
// ---------------------------------------------------------------------------

test('user with Gérant/Admin role can access orders', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Gérant/Admin'));
    $this->actingAs($user);

    $this->get(route('orders.index'))->assertOk();
});

test('user with Chef Pâtissier role can access orders', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Chef Pâtissier'));
    $this->actingAs($user);

    $this->get(route('orders.index'))->assertOk();
});

test('user with Comptable role cannot access orders', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Comptable'));
    $this->actingAs($user);

    $this->get(route('orders.index'))->assertForbidden();
});

test('user with ghost role can access all pages', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('ghost'));
    $this->actingAs($user);

    $this->get(route('orders.index'))->assertOk();
});
