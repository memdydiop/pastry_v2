<?php

use App\Enums\ClientGender;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

if (! function_exists('clientUser')) {
    function clientUser(): User
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

test('client can be created with valid data', function () {
    $client = createClient([
        'name' => 'Kouamé Yao',
        'phone' => '+225 01 02 03 04 05',
        'email' => 'yao@example.com',
        'gender' => 'M',
        'notes' => 'Client fidèle',
    ]);

    expect($client->name)->toBe('Kouamé Yao');
    expect($client->phone)->toBe('+225 01 02 03 04 05');
    expect($client->email)->toBe('yao@example.com');
    expect($client->gender)->toBe(ClientGender::M);
    expect($client->notes)->toBe('Client fidèle');
});

test('client phone must be unique', function () {
    createClient(['phone' => '+225 01 02 03 04 05']);

    expect(fn () => createClient(['phone' => '+225 01 02 03 04 05']))
        ->toThrow(QueryException::class);
});

test('client email is nullable', function () {
    $client = createClient(['email' => null]);

    expect($client->email)->toBeNull();
});

test('client notes is nullable', function () {
    $client = createClient(['notes' => null]);

    expect($client->notes)->toBeNull();
});

test('client gender enum values are correct', function () {
    expect(ClientGender::M->value)->toBe('M');
    expect(ClientGender::MME->value)->toBe('Mme');
});

test('client can have orders', function () {
    $client = createClient();
    $order = Order::factory()->create(['client_id' => $client->id]);

    expect($client->orders)->toHaveCount(1);
    expect($client->orders->first()->id)->toBe($order->id);
});

test('deleting client cascades to orders', function () {
    $client = createClient();
    $order = Order::factory()->create(['client_id' => $client->id]);

    $client->delete();

    expect(Client::find($client->id))->toBeNull();
    expect(Order::find($order->id))->toBeNull();
});

test('clients are ordered by name ascending', function () {
    createClient(['name' => 'Zadi']);
    createClient(['name' => 'Aka']);
    createClient(['name' => 'Bamba']);

    $names = Client::orderBy('name', 'asc')->pluck('name')->toArray();
    expect($names)->toBe(['Aka', 'Bamba', 'Zadi']);
});

test('guest is redirected when accessing client page', function () {
    $this->get(route('clients.index'))->assertRedirect(route('login'));
});

test('user with client role can access client page', function () {
    $user = clientUser();
    $this->actingAs($user);

    $this->get(route('clients.index'))->assertOk();
});

test('user without role cannot access client page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('clients.index'))->assertForbidden();
});

test('client page displays list of clients', function () {
    $user = clientUser();
    $this->actingAs($user);

    createClient(['name' => 'Aka']);
    createClient(['name' => 'Bamba']);

    Livewire::test('pages::clients.index')
        ->assertViewHas('clients', fn ($p) => $p->total() === 2);
});

test('client page filters by name search', function () {
    $user = clientUser();
    $this->actingAs($user);

    createClient(['name' => 'Kouamé Yao']);
    createClient(['name' => 'Fatou N\'Diaye']);

    Livewire::test('pages::clients.index')
        ->set('search', 'Kouamé')
        ->assertViewHas('clients', fn ($p) => $p->total() === 1);
});

test('client page filters by phone search', function () {
    $user = clientUser();
    $this->actingAs($user);

    createClient(['name' => 'Aka', 'phone' => '+225 01 02 03 04 05']);
    createClient(['name' => 'Bamba', 'phone' => '+225 07 08 09 00 00']);

    Livewire::test('pages::clients.index')
        ->set('search', '04 05')
        ->assertViewHas('clients', fn ($p) => $p->total() === 1);
});

test('client can be created via modal', function () {
    $user = clientUser();
    $this->actingAs($user);

    Livewire::test('pages::clients.modals.client-form-modal')
        ->dispatch('open-client-modal')
        ->set('name', 'Nouveau Client')
        ->set('phone', '+225 01 02 03 04 05')
        ->set('gender', 'M')
        ->call('saveClient');

    expect(Client::where('name', 'Nouveau Client')->exists())->toBeTrue();
});

test('client creation requires valid data', function () {
    $user = clientUser();
    $this->actingAs($user);

    Livewire::test('pages::clients.modals.client-form-modal')
        ->dispatch('open-client-modal')
        ->call('saveClient')
        ->assertHasErrors(['name', 'phone', 'gender']);
});

test('client creation validates phone uniqueness', function () {
    $user = clientUser();
    $this->actingAs($user);

    createClient(['phone' => '+225 01 02 03 04 05']);

    Livewire::test('pages::clients.modals.client-form-modal')
        ->dispatch('open-client-modal')
        ->set('name', 'Duplicat')
        ->set('phone', '+225 01 02 03 04 05')
        ->set('gender', 'Mme')
        ->call('saveClient')
        ->assertHasErrors(['phone' => 'unique']);
});

test('client can be edited via modal', function () {
    $user = clientUser();
    $this->actingAs($user);

    $client = createClient(['name' => 'Ancien nom', 'phone' => '+225 01 02 03 04 05']);

    Livewire::test('pages::clients.modals.client-form-modal')
        ->set('client_id', $client->id)
        ->set('name', 'Nouveau nom')
        ->set('phone', $client->phone)
        ->set('gender', 'M')
        ->call('saveClient');

    expect($client->fresh()->name)->toBe('Nouveau nom');
});

test('client edit keeps same phone unique validation', function () {
    $user = clientUser();
    $this->actingAs($user);

    $client = createClient(['name' => 'Client', 'phone' => '+225 01 02 03 04 05', 'gender' => 'Mme']);

    Livewire::test('pages::clients.modals.client-form-modal')
        ->set('client_id', $client->id)
        ->set('name', 'Client modifié')
        ->set('phone', $client->phone)
        ->set('gender', 'Mme')
        ->call('saveClient')
        ->assertHasNoErrors();
});

test('client can be deleted', function () {
    $user = clientUser();
    $this->actingAs($user);

    $client = createClient(['name' => 'À supprimer']);

    Livewire::test('pages::clients.index')
        ->call('prepareDeleteClient', $client->id)
        ->call('confirmDeleteClient');

    expect(Client::find($client->id))->toBeNull();
});

test('non-admin cannot delete client', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Caissier'));
    $this->actingAs($user);

    $client = createClient();

    Livewire::test('pages::clients.index')
        ->call('prepareDeleteClient', $client->id)
        ->call('confirmDeleteClient')
        ->assertForbidden();
});

test('Gérant/Admin can access client page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Gérant/Admin'));
    $this->actingAs($user);

    $this->get(route('clients.index'))->assertOk();
});

test('Chef Pâtissier can access client page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Chef Pâtissier'));
    $this->actingAs($user);

    $this->get(route('clients.index'))->assertOk();
});

test('Pâtissier can access client page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Pâtissier'));
    $this->actingAs($user);

    $this->get(route('clients.index'))->assertOk();
});

test('Caissier can access client page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Caissier'));
    $this->actingAs($user);

    $this->get(route('clients.index'))->assertOk();
});

test('Comptable cannot access client page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Comptable'));
    $this->actingAs($user);

    $this->get(route('clients.index'))->assertForbidden();
});
