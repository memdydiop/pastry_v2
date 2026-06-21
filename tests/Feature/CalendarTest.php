<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Gérant/Admin');
});

test('guest cannot access calendar page', function () {
    $this->get(route('production.calendar'))->assertRedirect('/login');
});

test('user without role cannot access calendar page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('production.calendar'))->assertForbidden();
});

test('Gérant/Admin can view calendar page', function () {
    $user = User::factory()->create();
    $user->assignRole('Gérant/Admin');
    $this->actingAs($user);

    $this->get(route('production.calendar'))
        ->assertOk()
        ->assertSee('Calendrier de Production');
});

test('calendar page shows orders for the week', function () {
    $user = User::factory()->create();
    $user->assignRole('Gérant/Admin');
    $this->actingAs($user);

    $deliveryDate = now()->startOfWeek()->addDays(2);
    $client = \App\Models\Client::factory()->create(['name' => 'Jean Test Calendar']);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'client_name' => 'Jean Test Calendar',
        'status' => OrderStatus::CONFIRMÉE,
        'delivery_due_at' => $deliveryDate,
    ]);

    Livewire::test('pages::production.calendar')
        ->assertSee('Jean Test Calendar')
        ->assertSee($order->reference);
});

test('calendar navigation works', function () {
    $user = User::factory()->create();
    $user->assignRole('Gérant/Admin');
    $this->actingAs($user);

    Livewire::test('pages::production.calendar')
        ->call('previousWeek')
        ->assertSet('weekStart', now()->startOfWeek()->subWeek()->format('Y-m-d'))
        ->call('nextWeek')
        ->assertSet('weekStart', now()->startOfWeek()->format('Y-m-d'))
        ->call('currentWeek')
        ->assertSet('weekStart', now()->startOfWeek()->format('Y-m-d'));
});
