<?php

use App\Models\DeliveryPartner;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Gérant/Admin');
    Role::findOrCreate('ghost');
});

test('guest cannot access delivery partners page', function () {
    $this->get(route('delivery.index'))->assertRedirect('/login');
});

test('user without role cannot access delivery partners page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('delivery.index'))->assertForbidden();
});

test('Gérant/Admin can view delivery partners page', function () {
    $user = User::factory()->create();
    $user->assignRole('Gérant/Admin');
    $this->actingAs($user);

    DeliveryPartner::factory()->create(['name' => 'Test Livreur']);

    $this->get(route('delivery.index'))
        ->assertOk()
        ->assertSee('Test Livreur');
});

test('Gérant/Admin can create a delivery partner', function () {
    $user = User::factory()->create();
    $user->assignRole('Gérant/Admin');
    $this->actingAs($user);

    $this->get(route('delivery.index'))->assertOk();

    Livewire::test('pages::delivery.index')
        ->set('name', 'Nouveau Livreur')
        ->set('phone', '690000000')
        ->set('email', 'livreur@test.com')
        ->set('vehicle_type', 'Camion')
        ->set('base_rate', 5000)
        ->call('save')
        ->assertDispatched('toast');

    $this->assertDatabaseHas('delivery_partners', [
        'name' => 'Nouveau Livreur',
        'phone' => '690000000',
    ]);
});

test('Gérant/Admin can update a delivery partner', function () {
    $user = User::factory()->create();
    $user->assignRole('Gérant/Admin');
    $this->actingAs($user);

    $partner = DeliveryPartner::factory()->create(['name' => 'Ancien Nom']);

    Livewire::test('pages::delivery.index')
        ->call('openModal', $partner->id)
        ->set('name', 'Nom Modifié')
        ->call('save')
        ->assertDispatched('toast');

    expect($partner->fresh()->name)->toBe('Nom Modifié');
});

test('Gérant/Admin can delete a delivery partner', function () {
    $user = User::factory()->create();
    $user->assignRole('Gérant/Admin');
    $this->actingAs($user);

    $partner = DeliveryPartner::factory()->create();

    Livewire::test('pages::delivery.index')
        ->call('prepareDelete', $partner->id)
        ->call('confirmDelete')
        ->assertDispatched('toast');

    $this->assertDatabaseMissing('delivery_partners', ['id' => $partner->id]);
});

test('ghost can delete a delivery partner', function () {
    $user = User::factory()->create();
    $user->assignRole('ghost');
    $this->actingAs($user);

    $partner = DeliveryPartner::factory()->create();

    Livewire::test('pages::delivery.index')
        ->call('prepareDelete', $partner->id)
        ->call('confirmDelete')
        ->assertDispatched('toast');

    $this->assertDatabaseMissing('delivery_partners', ['id' => $partner->id]);
});

test('delivery partner search filters correctly', function () {
    $user = User::factory()->create();
    $user->assignRole('Gérant/Admin');
    $this->actingAs($user);

    DeliveryPartner::factory()->create(['name' => 'Alpha Livreur']);
    DeliveryPartner::factory()->create(['name' => 'Beta Express']);

    Livewire::test('pages::delivery.index')
        ->set('search', 'Alpha')
        ->assertSee('Alpha Livreur')
        ->assertDontSee('Beta Express');
});
