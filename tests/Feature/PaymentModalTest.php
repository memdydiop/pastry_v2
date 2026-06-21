<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

if (! function_exists('paymentUser')) {
    function paymentUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Gérant/Admin'));

        return $user;
    }
}

if (! function_exists('createPaymentOrder')) {
    function createPaymentOrder(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'total_amount' => 50000,
            'status' => OrderStatus::EN_ATTENTE,
        ], $overrides));
    }
}

beforeEach(function () {
    Role::findOrCreate('Gérant/Admin');
    Role::findOrCreate('Pâtissier');
    Role::findOrCreate('Caissier');
    Role::findOrCreate('Chef Pâtissier');
    Role::findOrCreate('Comptable');
});

test('modal initializes with defaults and order data', function () {
    $user = paymentUser();
    $this->actingAs($user);

    $order = createPaymentOrder();

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->assertSet('showModal', true)
        ->assertSet('orderId', $order->id)
        ->assertSet('payment_method', 'Espèces')
        ->assertSet('amount', 0)
        ->assertSet('external_ref', '')
        ->assertSet('notes', '')
        ->assertSet('paid_at', now()->format('Y-m-d\TH:i'))
        ->assertViewHas('order', fn ($o) => $o->id === $order->id);
});

test('modal resets fields when reopened', function () {
    $user = paymentUser();
    $this->actingAs($user);

    $order = createPaymentOrder();

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 25000)
        ->set('payment_method', 'Carte bancaire')
        ->set('external_ref', 'TXN-123')
        ->set('notes', 'Test note')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->assertSet('amount', 0)
        ->assertSet('payment_method', 'Espèces')
        ->assertSet('external_ref', '')
        ->assertSet('notes', '');
});

test('savePayment validates required fields', function () {
    $user = paymentUser();
    $this->actingAs($user);

    $order = createPaymentOrder();

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 0)
        ->call('savePayment')
        ->assertHasErrors(['amount']);
});

test('savePayment validates payment_method is valid enum', function () {
    $user = paymentUser();
    $this->actingAs($user);

    $order = createPaymentOrder();

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 10000)
        ->set('paid_at', now()->format('Y-m-d\TH:i'))
        ->set('payment_method', 'Bitcoin')
        ->call('savePayment')
        ->assertHasErrors(['payment_method']);
});

test('savePayment rejects amount exceeding remaining balance', function () {
    $user = paymentUser();
    $this->actingAs($user);

    $order = createPaymentOrder(['total_amount' => 50000]);

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 60000)
        ->set('paid_at', now()->format('Y-m-d\TH:i'))
        ->call('savePayment')
        ->assertHasErrors(['amount']);
});

test('savePayment creates transaction and dispatches events', function () {
    $user = paymentUser();
    $this->actingAs($user);

    $order = createPaymentOrder(['total_amount' => 50000]);

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 25000)
        ->set('paid_at', now()->format('Y-m-d\TH:i'))
        ->set('payment_method', 'Wave')
        ->set('external_ref', 'WAVE-123ABC')
        ->set('notes', 'Paiement acompte')
        ->call('savePayment')
        ->assertHasNoErrors()
        ->assertDispatched('toast')
        ->assertDispatched('order-saved')
        ->assertSet('showModal', false);

    $transaction = Transaction::where('order_id', $order->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->amount)->toEqual(25000);
    expect($transaction->payment_method)->toEqual(PaymentMethod::WAVE);
    expect($transaction->external_ref)->toEqual('WAVE-123ABC');
    expect($transaction->notes)->toEqual('Paiement acompte');
    expect($transaction->type->value)->toEqual('payment');
    expect($transaction->user_id)->toEqual($user->id);
    expect($transaction->reference)->toStartWith('Paiement-');
});

test('savePayment changes status from EN_ATTENTE to ACOMPTE_PERÇU on first payment', function () {
    $user = paymentUser();
    $this->actingAs($user);

    $order = createPaymentOrder([
        'total_amount' => 50000,
        'status' => OrderStatus::EN_ATTENTE,
    ]);

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 25000)
        ->set('paid_at', now()->format('Y-m-d\TH:i'))
        ->call('savePayment')
        ->assertHasNoErrors();

    expect($order->refresh()->status->value)->toEqual(OrderStatus::ACOMPTE_PERÇU->value);
});

test('savePayment does not change status if order is already confirmed', function () {
    $user = paymentUser();
    $this->actingAs($user);

    $order = createPaymentOrder([
        'total_amount' => 50000,
        'status' => OrderStatus::CONFIRMÉE,
    ]);

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 25000)
        ->set('paid_at', now()->format('Y-m-d\TH:i'))
        ->call('savePayment')
        ->assertHasNoErrors();

    expect($order->refresh()->status->value)->toEqual(OrderStatus::CONFIRMÉE->value);
});

test('savePayment accepts full remaining amount', function () {
    $user = paymentUser();
    $this->actingAs($user);

    $order = createPaymentOrder(['total_amount' => 50000]);

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 50000)
        ->set('paid_at', now()->format('Y-m-d\TH:i'))
        ->call('savePayment')
        ->assertHasNoErrors();
});

test('multiple payments can be recorded on same order', function () {
    $user = paymentUser();
    $this->actingAs($user);

    $order = createPaymentOrder(['total_amount' => 50000]);

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 25000)
        ->set('paid_at', now()->format('Y-m-d\TH:i'))
        ->call('savePayment');

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 25000)
        ->set('paid_at', now()->format('Y-m-d\TH:i'))
        ->call('savePayment')
        ->assertHasNoErrors();

    expect($order->transactions()->count())->toEqual(2);
    expect($order->total_paid)->toEqual(50000.0);
});

test('guest cannot access payment modal', function () {
    $order = createPaymentOrder();

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->assertSet('showModal', true);

    $this->assertEquals(0, Transaction::count());
});

test('savePayment validates external_ref max length', function () {
    $user = paymentUser();
    $this->actingAs($user);

    $order = createPaymentOrder();

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 10000)
        ->set('paid_at', now()->format('Y-m-d\TH:i'))
        ->set('external_ref', str_repeat('a', 256))
        ->call('savePayment')
        ->assertHasErrors(['external_ref']);
});

test('savePayment validates notes max length', function () {
    $user = paymentUser();
    $this->actingAs($user);

    $order = createPaymentOrder();

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 10000)
        ->set('paid_at', now()->format('Y-m-d\TH:i'))
        ->set('notes', str_repeat('a', 501))
        ->call('savePayment')
        ->assertHasErrors(['notes']);
});
