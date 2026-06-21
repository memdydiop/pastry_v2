<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

if (! function_exists('unpaidUser')) {
    function unpaidUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Gérant/Admin'));

        return $user;
    }
}

if (! function_exists('createUnpaidOrder')) {
    function createUnpaidOrder(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'total_amount' => 50000,
            'status' => OrderStatus::CONFIRMÉE,
        ], $overrides));
    }
}

beforeEach(function () {
    Gate::define('cancel-transaction', fn ($user) => true);
    Gate::define('edit-transaction', fn ($user) => true);
});

test('guest is redirected when accessing unpaid page', function () {
    $this->get(route('transactions.unpaid'))->assertRedirect(route('login'));
});

test('user without role cannot access unpaid page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('transactions.unpaid'))->assertForbidden();
});

test('Gérant/Admin can access unpaid page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Gérant/Admin'));
    $this->actingAs($user);

    $this->get(route('transactions.unpaid'))->assertOk();
});

test('Comptable can access unpaid page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Comptable'));
    $this->actingAs($user);

    $this->get(route('transactions.unpaid'))->assertOk();
});

test('Caissier can access unpaid page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Caissier'));
    $this->actingAs($user);

    $this->get(route('transactions.unpaid'))->assertOk();
});

test('Chef Pâtissier cannot access unpaid page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Chef Pâtissier'));
    $this->actingAs($user);

    $this->get(route('transactions.unpaid'))->assertForbidden();
});

test('Pâtissier cannot access unpaid page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Pâtissier'));
    $this->actingAs($user);

    $this->get(route('transactions.unpaid'))->assertForbidden();
});

test('unpaid page shows empty state when no unpaid orders', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    Livewire::test('pages::transactions.unpaid')
        ->assertViewHas('transactions', fn ($p) => $p->total() === 0)
        ->assertViewHas('unsettledOrders', 0)
        ->assertViewHas('totalOutstanding', 0)
        ->assertViewHas('totalPayments', 0);
});

test('unpaid page shows payment for unpaid order', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    $order = createUnpaidOrder(['total_amount' => 50000]);
    Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 20000,
        'paid_at' => now(),
    ]);

    Livewire::test('pages::transactions.unpaid')
        ->assertViewHas('transactions', fn ($p) => $p->total() === 1)
        ->assertViewHas('unsettledOrders', 1);
});

test('unpaid page shows correct KPIs', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    $order1 = createUnpaidOrder(['total_amount' => 50000]);
    $order2 = createUnpaidOrder(['total_amount' => 75000]);

    Transaction::factory()->create([
        'order_id' => $order1->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 20000,
        'paid_at' => now(),
    ]);
    Transaction::factory()->create([
        'order_id' => $order2->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 50000,
        'paid_at' => now(),
    ]);

    Livewire::test('pages::transactions.unpaid')
        ->assertViewHas('totalPayments', 70000)
        ->assertViewHas('unsettledOrders', 2);
});

test('unpaid page excludes fully paid orders', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    $order = createUnpaidOrder(['total_amount' => 30000]);
    Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 30000,
        'paid_at' => now(),
    ]);

    Livewire::test('pages::transactions.unpaid')
        ->assertViewHas('transactions', fn ($p) => $p->total() === 0)
        ->assertViewHas('unsettledOrders', 0);
});

test('unpaid page excludes cancelled payments', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    $order = createUnpaidOrder(['total_amount' => 50000]);
    $txn = Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 20000,
        'paid_at' => now(),
    ]);
    $txn->cancel('Test cancellation');

    Livewire::test('pages::transactions.unpaid')
        ->assertViewHas('transactions', fn ($p) => $p->total() === 0);
});

test('unpaid page filters by search on reference', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    $order = createUnpaidOrder();
    $txn1 = Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 10000,
        'paid_at' => now(),
        'reference' => 'ABC-001',
    ]);
    $txn2 = Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 5000,
        'paid_at' => now(),
        'reference' => 'XYZ-002',
    ]);

    Livewire::test('pages::transactions.unpaid')
        ->set('search', 'ABC')
        ->assertViewHas('transactions', fn ($p) => $p->total() === 1);
});

test('unpaid page filters by payment method', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    $order = createUnpaidOrder(['total_amount' => 100000]);
    Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 10000,
        'payment_method' => PaymentMethod::WAVE->value,
        'paid_at' => now(),
    ]);
    Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 5000,
        'payment_method' => PaymentMethod::ESPÈCES->value,
        'paid_at' => now(),
    ]);

    Livewire::test('pages::transactions.unpaid')
        ->set('methodFilter', PaymentMethod::WAVE->value)
        ->assertViewHas('transactions', fn ($p) => $p->total() === 1);
});

test('unpaid page filters by date range', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    $order = createUnpaidOrder(['total_amount' => 100000]);
    Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 10000,
        'paid_at' => now()->subDays(5),
    ]);
    Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 5000,
        'paid_at' => now()->subDays(15),
    ]);

    Livewire::test('pages::transactions.unpaid')
        ->set('dateFrom', now()->subDays(10)->format('Y-m-d'))
        ->set('dateTo', now()->format('Y-m-d'))
        ->assertViewHas('transactions', fn ($p) => $p->total() === 1);
});

test('clearFilters resets all filters', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    $order = createUnpaidOrder(['total_amount' => 100000]);
    Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 10000,
        'paid_at' => now(),
    ]);

    Livewire::test('pages::transactions.unpaid')
        ->set('search', 'test')
        ->set('methodFilter', PaymentMethod::WAVE->value)
        ->set('dateFrom', now()->format('Y-m-d'))
        ->set('dateTo', now()->format('Y-m-d'))
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('methodFilter', '')
        ->assertSet('dateFrom', '')
        ->assertSet('dateTo', '');
});

test('openCancelModal loads transaction and shows modal', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    $order = createUnpaidOrder(['total_amount' => 50000]);
    $txn = Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 20000,
        'paid_at' => now(),
    ]);

    Livewire::test('pages::transactions.unpaid')
        ->call('openCancelModal', $txn->id)
        ->assertSet('showCancelModal', true)
        ->assertSet('cancellingTransaction.id', $txn->id);
});

test('processCancel validates reason', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    $order = createUnpaidOrder(['total_amount' => 50000]);
    $txn = Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 20000,
        'paid_at' => now(),
    ]);

    Livewire::test('pages::transactions.unpaid')
        ->call('openCancelModal', $txn->id)
        ->set('cancellationReason', '')
        ->call('processCancel')
        ->assertHasErrors(['cancellationReason']);
});

test('processCancel cancels transaction and closes modal', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    $order = createUnpaidOrder(['total_amount' => 50000]);
    $txn = Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 20000,
        'paid_at' => now(),
    ]);

    Livewire::test('pages::transactions.unpaid')
        ->call('openCancelModal', $txn->id)
        ->set('cancellationReason', 'Paiement enregistré par erreur')
        ->call('processCancel')
        ->assertSet('showCancelModal', false);

    expect($txn->fresh()->isCancelled())->toBeTrue();
});

test('openEditModal loads transaction and shows modal', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    $order = createUnpaidOrder(['total_amount' => 50000]);
    $txn = Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 20000,
        'payment_method' => PaymentMethod::ESPÈCES->value,
        'paid_at' => now(),
    ]);

    Livewire::test('pages::transactions.unpaid')
        ->call('openEditModal', $txn->id)
        ->assertSet('showEditModal', true)
        ->assertSet('editAmount', 20000.0)
        ->assertSet('editMethod', PaymentMethod::ESPÈCES->value);
});

test('processEdit validates required fields', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    $order = createUnpaidOrder(['total_amount' => 50000]);
    $txn = Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 20000,
        'paid_at' => now(),
    ]);

    Livewire::test('pages::transactions.unpaid')
        ->call('openEditModal', $txn->id)
        ->set('editAmount', 0)
        ->set('editMethod', '')
        ->call('processEdit')
        ->assertHasErrors(['editAmount', 'editMethod']);
});

test('processEdit updates transaction and closes modal', function () {
    $user = unpaidUser();
    $this->actingAs($user);

    $order = createUnpaidOrder(['total_amount' => 50000]);
    $txn = Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => TransactionType::PAYMENT,
        'amount' => 20000,
        'payment_method' => PaymentMethod::ESPÈCES->value,
        'paid_at' => now(),
    ]);

    Livewire::test('pages::transactions.unpaid')
        ->call('openEditModal', $txn->id)
        ->set('editAmount', 25000)
        ->set('editMethod', PaymentMethod::WAVE->value)
        ->call('processEdit')
        ->assertSet('showEditModal', false);

    $txn->refresh();
    expect($txn->amount)->toEqual(25000.0);
    expect($txn->payment_method->value)->toBe(PaymentMethod::WAVE->value);
});
