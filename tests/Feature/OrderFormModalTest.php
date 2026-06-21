<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderImage;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

if (! function_exists('orderFormUser')) {
    function orderFormUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Gérant/Admin'));

        return $user;
    }
}

if (! function_exists('createClient')) {
    function createClient(array $overrides = []): Client
    {
        return Client::factory()->create($overrides);
    }
}

test('modal initializes with defaults for new order', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->assertSet('editingOrderId', null)
        ->assertSet('tiers_count', 1)
        ->assertSet('delivery_time', '12:00')
        ->assertSet('payment_method', 'Espèces')
        ->assertSet('status', 'En attente')
        ->assertSet('total_amount', 0)
        ->assertSet('showModal', true);
});

test('modal loads existing order data for editing', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    $client = createClient();
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'total_amount' => 75000,
        'tiers_count' => 2,
    ]);
    $order->levels()->createMany([
        ['level_number' => 1, 'flavor_biscuit' => 'Vanille', 'flavor_cream' => 'Chantilly'],
        ['level_number' => 2, 'flavor_biscuit' => 'Chocolat', 'flavor_cream' => 'Praliné'],
    ]);

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal', id: $order->id)
        ->assertSet('editingOrderId', $order->id)
        ->assertSet('client_id', (string) $client->id)
        ->assertSet('total_amount', 75000.0)
        ->assertSet('tiers_count', 2)
        ->assertSet('showModal', true)
        ->assertSet('levels', fn ($levels) => count($levels) === 2);
});

test('updatedClientId sets client_id', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    $client = createClient();

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->set('client_id', (string) $client->id)
        ->assertSet('client_id', (string) $client->id);
});

test('updatedClientId clears client_id when empty', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->set('client_id', '')
        ->assertSet('client_id', '');
});

test('updatedTiersCount syncs levels up when increased', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->assertSet('levels', fn ($levels) => count($levels) === 1)
        ->set('tiers_count', 3)
        ->assertSet('levels', fn ($levels) => count($levels) === 3);
});

test('updatedTiersCount syncs levels down when decreased', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->set('tiers_count', 4)
        ->assertSet('levels', fn ($levels) => count($levels) === 4)
        ->set('tiers_count', 2)
        ->assertSet('levels', fn ($levels) => count($levels) === 2);
});

test('levels have sequential level_numbers after sync', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->set('tiers_count', 3)
        ->assertSet('levels', fn ($levels) => (
            $levels[0]['level_number'] === 1
            && $levels[1]['level_number'] === 2
            && $levels[2]['level_number'] === 3
        ));
});

test('saveOrder creates new order with levels', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    $client = createClient();

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->set('client_id', (string) $client->id)
        ->set('total_amount', 50000)
        ->set('tiers_count', 1)
        ->call('saveOrder');

    $order = Order::where('client_id', $client->id)->first();
    expect($order)->not->toBeNull();
    expect($order->total_amount)->toEqual(50000.0);
    expect($order->levels)->toHaveCount(1);
});

test('saveOrder stores contact_phone_2 and contact_phone_3', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    $client = createClient();

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->set('client_id', (string) $client->id)
        ->set('contact_phone_2', '0102030405')
        ->set('contact_phone_3', '0607080910')
        ->set('total_amount', 35000)
        ->call('saveOrder');

    $order = Order::where('client_id', $client->id)->first();
    expect($order->contact_phone_2)->toBe('0102030405');
    expect($order->contact_phone_3)->toBe('0607080910');
});

test('saveOrder creates order with multiple levels', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    $client = createClient();

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->set('client_id', (string) $client->id)
        ->set('total_amount', 85000)
        ->set('tiers_count', 3)
        ->set('levels', [
            ['level_number' => 1, 'shape' => 'Rond', 'flavor_biscuit' => 'Vanille', 'flavor_cream' => 'Chantilly', 'filling' => '', 'diameter_cm' => '', 'width_cm' => '', 'length_cm' => '', 'height_cm' => '', 'notes' => ''],
            ['level_number' => 2, 'shape' => 'Rond', 'flavor_biscuit' => 'Chocolat', 'flavor_cream' => 'Praliné', 'filling' => '', 'diameter_cm' => '', 'width_cm' => '', 'length_cm' => '', 'height_cm' => '', 'notes' => ''],
            ['level_number' => 3, 'shape' => 'Carré', 'flavor_biscuit' => 'Fraise', 'flavor_cream' => 'Vanille', 'filling' => '', 'diameter_cm' => '', 'width_cm' => '', 'length_cm' => '', 'height_cm' => '', 'notes' => ''],
        ])
        ->call('saveOrder');

    $order = Order::where('client_id', $client->id)->first();
    expect($order->levels)->toHaveCount(3);
});

test('saveOrder validates required fields', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->set('client_id', '')
        ->set('delivery_date', '')
        ->set('delivery_time', '')
        ->call('saveOrder')
        ->assertHasErrors(['client_id', 'delivery_date', 'delivery_time']);
});

test('saveOrder validates tiers_count minimum', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    $client = createClient();

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->set('client_id', (string) $client->id)
        ->set('total_amount', 50000)
        ->set('tiers_count', 0)
        ->call('saveOrder')
        ->assertHasErrors(['tiers_count']);
});

test('saveOrder updates existing order and replaces levels', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    $client = createClient();
    $order = Order::factory()->create(['client_id' => $client->id, 'total_amount' => 30000]);
    $order->levels()->create(['level_number' => 1, 'flavor_biscuit' => 'Ancien']);

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal', id: $order->id)
        ->set('total_amount', 60000)
        ->set('levels', [
            ['level_number' => 1, 'shape' => 'Rond', 'flavor_biscuit' => 'Nouveau', 'flavor_cream' => '', 'filling' => '', 'diameter_cm' => '', 'width_cm' => '', 'length_cm' => '', 'height_cm' => '', 'notes' => ''],
        ])
        ->call('saveOrder');

    expect($order->fresh()->total_amount)->toEqual(60000.0);
    expect($order->fresh()->levels)->toHaveCount(1);
    expect($order->fresh()->levels->first()->flavor_biscuit)->toBe('Nouveau');
});

test('saveOrder dispatches order-saved event', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    $client = createClient();

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->set('client_id', (string) $client->id)
        ->set('total_amount', 45000)
        ->call('saveOrder')
        ->assertDispatched('order-saved');
});

test('saveOrder closes modal on success', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    $client = createClient();

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->set('client_id', (string) $client->id)
        ->set('total_amount', 45000)
        ->call('saveOrder')
        ->assertSet('showModal', false);
});

test('removeImage deletes from storage and removes from existingImages', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    $order = Order::factory()->create();
    $image = $order->images()->create([
        'file_path' => 'order-images/test.jpg',
        'original_name' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
    ]);

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal', id: $order->id)
        ->call('removeImage', $image->id)
        ->assertSet('existingImages', fn ($images) => $images->count() === 0);

    expect(OrderImage::find($image->id))->toBeNull();
});

test('handleClientCreated sets client_id and auto-fills', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    $client = createClient(['name' => 'Nouveau Client', 'phone' => '+225 99 88 77']);

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->dispatch('client-saved', clientId: (string) $client->id)
        ->assertSet('client_id', (string) $client->id);
});

test('modal can be opened without parameters to create new order', function () {
    $user = orderFormUser();
    $this->actingAs($user);

    Livewire::test('pages::orders.modals.order-form-modal')
        ->dispatch('open-order-modal')
        ->assertSet('editingOrderId', null)
        ->assertSet('showModal', true);
});
