<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

if (! function_exists('financeUser')) {
    function financeUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Gérant/Admin'));

        return $user;
    }
}

if (! function_exists('createTransaction')) {
    function createTransaction(array $overrides = []): Transaction
    {
        return Transaction::factory()->create($overrides);
    }
}

// ---------------------------------------------------------------------------
// Model — TransactionType enum
// ---------------------------------------------------------------------------

test('transaction type enum values are correct', function () {
    expect(TransactionType::PAYMENT->value)->toBe('payment');
    expect(TransactionType::REFUND->value)->toBe('refund');
    expect(TransactionType::FEE->value)->toBe('fee');
});

// ---------------------------------------------------------------------------
// Model — PaymentMethod enum
// ---------------------------------------------------------------------------

test('payment method enum values and labels are correct', function () {
    expect(PaymentMethod::ESPÈCES->value)->toBe('Espèces');
    expect(PaymentMethod::WAVE->value)->toBe('Wave');
    expect(PaymentMethod::ORANGE_MONEY->value)->toBe('Orange Money');
    expect(PaymentMethod::MOOV_MONEY->value)->toBe('Moov Money');
    expect(PaymentMethod::CHÈQUE->value)->toBe('Chèque');
    expect(PaymentMethod::VIREMENT->value)->toBe('Virement');

    expect(PaymentMethod::ESPÈCES->label())->toBe('Espèces');
    expect(PaymentMethod::WAVE->label())->toBe('Wave');
    expect(PaymentMethod::VIREMENT->label())->toBe('Virement bancaire');
});

// ---------------------------------------------------------------------------
// Model — Création
// ---------------------------------------------------------------------------

test('payment transaction can be created with valid data', function () {
    $user = financeUser();
    $order = Order::factory()->create();
    $txn = createTransaction([
        'type' => TransactionType::PAYMENT,
        'order_id' => $order->id,
        'amount' => 25000,
        'payment_method' => PaymentMethod::WAVE->value,
        'paid_at' => now(),
        'user_id' => $user->id,
    ]);

    expect($txn->type)->toBe(TransactionType::PAYMENT);
    expect(floatval($txn->amount))->toEqual(25000.0);
    expect($txn->payment_method)->toBe(PaymentMethod::WAVE);
    expect($txn->user_id)->toBe($user->id);
});

test('refund transaction can be created', function () {
    $payment = createTransaction(['type' => TransactionType::PAYMENT]);
    $refund = createTransaction([
        'type' => TransactionType::REFUND,
        'parent_transaction_id' => $payment->id,
        'order_id' => $payment->order_id,
    ]);

    expect($refund->type)->toBe(TransactionType::REFUND);
    expect($refund->parent_transaction_id)->toBe($payment->id);
});

test('transaction generates unique reference', function () {
    $ref1 = Transaction::generateReference('Paiement');
    expect($ref1)->toMatch('/^Paiement-\d{6}-\d{4}$/');

    Transaction::factory()->create(['reference' => $ref1]);
    $ref2 = Transaction::generateReference('Paiement');
    expect($ref2)->toMatch('/^Paiement-\d{6}-\d{4}$/');
    expect($ref1)->not->toBe($ref2);

    Transaction::factory()->create(['reference' => $ref2]);
    $ref3 = Transaction::generateReference('Remb');
    expect($ref3)->toMatch('/^Remb-\d{6}-\d{4}$/');
});

// ---------------------------------------------------------------------------
// Model — Relations
// ---------------------------------------------------------------------------

test('transaction belongs to order', function () {
    $order = Order::factory()->create();
    $txn = createTransaction(['order_id' => $order->id]);

    expect($txn->order->id)->toBe($order->id);
});

test('transaction belongs to user', function () {
    $user = financeUser();
    $txn = createTransaction(['user_id' => $user->id]);

    expect($txn->user->id)->toBe($user->id);
});

test('transaction can have refunds', function () {
    $payment = createTransaction(['type' => TransactionType::PAYMENT]);
    $refund = createTransaction([
        'type' => TransactionType::REFUND,
        'parent_transaction_id' => $payment->id,
    ]);

    expect($payment->refunds)->toHaveCount(1);
    expect($payment->refunds->first()->id)->toBe($refund->id);
});

test('refund has parent transaction', function () {
    $payment = createTransaction(['type' => TransactionType::PAYMENT]);
    $refund = createTransaction([
        'type' => TransactionType::REFUND,
        'parent_transaction_id' => $payment->id,
    ]);

    expect($refund->parentTransaction->id)->toBe($payment->id);
});

// ---------------------------------------------------------------------------
// Model — Scopes
// ---------------------------------------------------------------------------

test('notCancelled scope excludes cancelled transactions', function () {
    $active = createTransaction();
    $cancelled = createTransaction();
    $cancelled->cancel('Test annulation');

    $result = Transaction::notCancelled()->get();

    expect($result->pluck('id'))->toContain($active->id);
    expect($result->pluck('id'))->not->toContain($cancelled->id);
});

test('cancelled scope returns only cancelled transactions', function () {
    $active = createTransaction();
    $cancelled = createTransaction();
    $cancelled->cancel('Test annulation');

    $result = Transaction::cancelled()->get();

    expect($result->pluck('id'))->toContain($cancelled->id);
    expect($result->pluck('id'))->not->toContain($active->id);
});

test('byType scope filters by transaction type', function () {
    createTransaction(['type' => TransactionType::PAYMENT]);
    createTransaction(['type' => TransactionType::REFUND]);

    $payments = Transaction::byType('payment')->get();

    expect($payments->every(fn ($t) => $t->type === TransactionType::PAYMENT))->toBeTrue();
});

test('byMethod scope filters by payment method', function () {
    createTransaction(['payment_method' => PaymentMethod::ESPÈCES->value]);
    createTransaction(['payment_method' => PaymentMethod::WAVE->value]);

    $wave = Transaction::byMethod('Wave')->get();

    expect($wave->every(fn ($t) => $t->payment_method === PaymentMethod::WAVE))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Model — Logique métier
// ---------------------------------------------------------------------------

test('isCancelled returns false for active transaction', function () {
    $txn = createTransaction();

    expect($txn->isCancelled())->toBeFalse();
});

test('isCancelled returns true after cancel', function () {
    $txn = createTransaction();
    $txn->cancel('Paiement erroné');

    expect($txn->fresh()->isCancelled())->toBeTrue();
    expect($txn->fresh()->cancellation_reason)->toBe('Paiement erroné');
});

test('cancel sets cancelled_at, cancelled_by and reason', function () {
    $user = financeUser();
    $this->actingAs($user);

    $txn = createTransaction();
    $txn->cancel('Erreur de montant');

    $fresh = $txn->fresh();
    expect($fresh->cancelled_at)->not->toBeNull();
    expect($fresh->cancelled_by)->toBe($user->id);
    expect($fresh->cancellation_reason)->toBe('Erreur de montant');
});

test('edit preserves old values for audit', function () {
    $user = financeUser();
    $this->actingAs($user);

    $txn = createTransaction([
        'payment_method' => PaymentMethod::ESPÈCES->value,
        'amount' => 10000,
        'external_ref' => null,
        'notes' => 'Note originale',
    ]);

    $txn->edit([
        'amount' => 15000,
        'payment_method' => PaymentMethod::WAVE->value,
    ]);

    $fresh = $txn->fresh();
    expect(floatval($fresh->amount))->toEqual(15000.0);
    expect($fresh->payment_method)->toBe(PaymentMethod::WAVE);
    expect($fresh->edited_by)->toBe($user->id);
    expect($fresh->edited_at)->not->toBeNull();

    $oldValues = $fresh->edit_old_values;
    expect($oldValues['amount'])->toEqual(10000);
    expect($oldValues['payment_method'])->toBe('Espèces');
});

// ---------------------------------------------------------------------------
// Livewire — Modal de paiement
// ---------------------------------------------------------------------------

test('payment modal can be opened', function () {
    $user = financeUser();
    $this->actingAs($user);

    $order = Order::factory()->create(['total_amount' => 50000]);

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->assertSet('orderId', $order->id)
        ->assertSet('showModal', true);
});

test('payment modal validates required fields', function () {
    $user = financeUser();
    $this->actingAs($user);

    $order = Order::factory()->create(['total_amount' => 50000]);

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 0)
        ->set('payment_method', '')
        ->set('paid_at', '')
        ->call('savePayment')
        ->assertHasErrors(['amount', 'payment_method', 'paid_at']);
});

test('payment modal validates amount does not exceed remaining balance', function () {
    $user = financeUser();
    $this->actingAs($user);

    $order = Order::factory()->create(['total_amount' => 50000]);

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 60000)
        ->set('payment_method', 'Espèces')
        ->set('paid_at', now()->format('Y-m-d\TH:i'))
        ->call('savePayment')
        ->assertHasErrors(['amount']);
});

test('payment modal records a payment', function () {
    $user = financeUser();
    $this->actingAs($user);

    $order = Order::factory()->create(['total_amount' => 50000]);

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 25000)
        ->set('payment_method', 'Wave')
        ->set('paid_at', now()->format('Y-m-d\TH:i'))
        ->call('savePayment');

    expect($order->transactions()->where('type', TransactionType::PAYMENT)->count())->toBe(1);
    expect(floatval($order->fresh()->total_paid))->toEqual(25000.0);
});

test('first payment transitions order to Acompte perçu', function () {
    $user = financeUser();
    $this->actingAs($user);

    $order = Order::factory()->create(['total_amount' => 50000, 'status' => OrderStatus::EN_ATTENTE]);

    Livewire::test('pages::orders.modals.payment-modal')
        ->dispatch('open-payment-modal', orderId: $order->id)
        ->set('amount', 15000)
        ->set('payment_method', 'Espèces')
        ->set('paid_at', now()->format('Y-m-d\TH:i'))
        ->call('savePayment');

    expect($order->fresh()->status)->toBe(OrderStatus::ACOMPTE_PERÇU);
});

// ---------------------------------------------------------------------------
// Livewire — Transactions Index
// ---------------------------------------------------------------------------

test('guest is redirected when accessing transactions page', function () {
    $this->get(route('transactions.index'))->assertRedirect(route('login'));
});

test('user without role cannot access transactions page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('transactions.index'))->assertForbidden();
});

test('transactions page displays list', function () {
    $user = financeUser();
    $this->actingAs($user);

    createTransaction();
    createTransaction();

    Livewire::test('pages::transactions.index')
        ->assertViewHas('transactions', fn ($p) => $p->total() === 2);
});

test('transactions page shows KPIs', function () {
    $user = financeUser();
    $this->actingAs($user);

    createTransaction(['type' => TransactionType::PAYMENT, 'amount' => 50000]);
    createTransaction(['type' => TransactionType::REFUND, 'amount' => 10000]);

    Livewire::test('pages::transactions.index')
        ->assertViewHas('totalPayments', fn ($v) => floatval($v) === 50000.0)
        ->assertViewHas('totalRefunds', fn ($v) => floatval($v) === 10000.0);
});

test('transactions page filters by type', function () {
    $user = financeUser();
    $this->actingAs($user);

    createTransaction(['type' => TransactionType::PAYMENT]);
    createTransaction(['type' => TransactionType::REFUND]);

    Livewire::test('pages::transactions.index')
        ->set('typeFilter', 'payment')
        ->assertViewHas('transactions', fn ($p) => $p->total() === 1);
});

test('transactions page filters by payment method', function () {
    $user = financeUser();
    $this->actingAs($user);

    createTransaction(['payment_method' => PaymentMethod::ESPÈCES->value]);
    createTransaction(['payment_method' => PaymentMethod::WAVE->value]);

    Livewire::test('pages::transactions.index')
        ->set('methodFilter', 'Wave')
        ->assertViewHas('transactions', fn ($p) => $p->total() === 1);
});

// ---------------------------------------------------------------------------
// Livewire — Remboursement
// ---------------------------------------------------------------------------

test('refund modal can be opened for a payment', function () {
    $user = financeUser();
    $this->actingAs($user);

    $order = Order::factory()->create(['total_amount' => 50000]);
    $payment = createTransaction([
        'type' => TransactionType::PAYMENT,
        'order_id' => $order->id,
        'amount' => 25000,
    ]);

    Livewire::test('pages::transactions.index')
        ->call('openRefundModal', $payment->id)
        ->assertSet('showRefundModal', true);
});

test('refund modal rejects cancelled payment', function () {
    $user = financeUser();
    $this->actingAs($user);

    $payment = createTransaction(['type' => TransactionType::PAYMENT]);
    $payment->cancel('Erreur');

    Livewire::test('pages::transactions.index')
        ->call('openRefundModal', $payment->id)
        ->assertSet('showRefundModal', false);
});

test('refund modal rejects refund type', function () {
    $user = financeUser();
    $this->actingAs($user);

    $refund = createTransaction(['type' => TransactionType::REFUND]);

    Livewire::test('pages::transactions.index')
        ->call('openRefundModal', $refund->id)
        ->assertSet('showRefundModal', false);
});

test('refund validates amount', function () {
    $user = financeUser();
    $this->actingAs($user);

    $order = Order::factory()->create(['total_amount' => 50000]);
    $payment = createTransaction([
        'type' => TransactionType::PAYMENT,
        'order_id' => $order->id,
        'amount' => 25000,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::transactions.index')
        ->call('openRefundModal', $payment->id)
        ->set('refundAmount', 0)
        ->call('processRefund')
        ->assertHasErrors(['refundAmount']);
});

test('refund creates a refund transaction', function () {
    $user = financeUser();
    $this->actingAs($user);

    $order = Order::factory()->create(['total_amount' => 50000]);
    $payment = createTransaction([
        'type' => TransactionType::PAYMENT,
        'order_id' => $order->id,
        'amount' => 25000,
        'user_id' => $user->id,
    ]);

    Livewire::test('pages::transactions.index')
        ->call('openRefundModal', $payment->id)
        ->set('refundAmount', 10000)
        ->set('refundMethod', 'Espèces')
        ->call('processRefund');

    expect($payment->fresh()->refunds)->toHaveCount(1);
    expect(floatval($payment->fresh()->refunds->first()->amount))->toEqual(10000.0);
});

// ---------------------------------------------------------------------------
// Livewire — Annulation
// ---------------------------------------------------------------------------

test('cancel modal validates reason', function () {
    $user = financeUser();
    $this->actingAs($user);

    $payment = createTransaction(['type' => TransactionType::PAYMENT]);

    Livewire::test('pages::transactions.index')
        ->call('openCancelModal', $payment->id)
        ->set('cancellationReason', '')
        ->call('processCancel')
        ->assertHasErrors(['cancellationReason']);
});

test('cancel modal processes cancellation', function () {
    $user = financeUser();
    $this->actingAs($user);

    $payment = createTransaction(['type' => TransactionType::PAYMENT]);

    Livewire::test('pages::transactions.index')
        ->call('openCancelModal', $payment->id)
        ->set('cancellationReason', 'Erreur de saisie')
        ->call('processCancel');

    expect($payment->fresh()->isCancelled())->toBeTrue();
    expect($payment->fresh()->cancellation_reason)->toBe('Erreur de saisie');
});

// ---------------------------------------------------------------------------
// Permissions
// ---------------------------------------------------------------------------

test('Gérant/Admin can access transactions', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Gérant/Admin'));
    $this->actingAs($user);

    $this->get(route('transactions.index'))->assertOk();
});

test('Comptable can access transactions', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Comptable'));
    $this->actingAs($user);

    $this->get(route('transactions.index'))->assertOk();
});

test('Caissier can access transactions', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Caissier'));
    $this->actingAs($user);

    $this->get(route('transactions.index'))->assertOk();
});

test('Chef Pâtissier cannot access transactions', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Chef Pâtissier'));
    $this->actingAs($user);

    $this->get(route('transactions.index'))->assertForbidden();
});

test('Pâtissier cannot access transactions', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Pâtissier'));
    $this->actingAs($user);

    $this->get(route('transactions.index'))->assertForbidden();
});
