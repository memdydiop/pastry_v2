<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Gérant/Admin');
});

test('guest cannot access invoices page', function () {
    $this->get(route('invoices.index'))->assertRedirect('/login');
});

test('user without role cannot access invoices page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('invoices.index'))->assertForbidden();
});

test('Gérant/Admin can view invoices page', function () {
    $user = User::factory()->create();
    $user->assignRole('Gérant/Admin');
    $this->actingAs($user);

    $client = \App\Models\Client::factory()->create(['name' => 'Client Facture']);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'client_name' => 'Client Facture',
        'status' => OrderStatus::LIVRÉE,
        'total_amount' => 25000,
    ]);

    Livewire::test('pages::invoices.index')
        ->assertSee('Client Facture')
        ->assertSee('Factures & Reçus');
});

test('invoices page shows correct totals', function () {
    $user = User::factory()->create();
    $user->assignRole('Gérant/Admin');
    $this->actingAs($user);

    Order::factory()->create([
        'status' => OrderStatus::LIVRÉE,
        'total_amount' => 30000,
    ]);
    Order::factory()->create([
        'status' => OrderStatus::CONFIRMÉE,
        'total_amount' => 20000,
    ]);

    Livewire::test('pages::invoices.index')
        ->assertSee('50 000');
});

test('invoices page search filters correctly', function () {
    $user = User::factory()->create();
    $user->assignRole('Gérant/Admin');
    $this->actingAs($user);

    $alpha = \App\Models\Client::factory()->create(['name' => 'Alpha Client']);
    $beta = \App\Models\Client::factory()->create(['name' => 'Beta Client']);
    Order::factory()->create([
        'client_id' => $alpha->id,
        'client_name' => 'Alpha Client',
        'status' => OrderStatus::LIVRÉE,
    ]);
    Order::factory()->create([
        'client_id' => $beta->id,
        'client_name' => 'Beta Client',
        'status' => OrderStatus::LIVRÉE,
    ]);

    Livewire::test('pages::invoices.index')
        ->set('search', 'Alpha')
        ->assertSee('Alpha Client')
        ->assertDontSee('Beta Client');
});
