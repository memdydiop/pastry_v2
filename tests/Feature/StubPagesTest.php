<?php

use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

if (! function_exists('stubAdminUser')) {
    function stubAdminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Gérant/Admin'));

        return $user;
    }
}

beforeEach(function () {
    Permission::findOrCreate('manage-settings');
    Role::findOrCreate('Gérant/Admin')
        ->givePermissionTo(Permission::all());
    Role::findOrCreate('Chef Pâtissier');
    Role::findOrCreate('Comptable');
    Role::findOrCreate('Caissier');
});

// ---------------------------------------------------------------------------
// Invoices
// ---------------------------------------------------------------------------

test('guest is redirected when accessing invoices page', function () {
    $this->get(route('invoices.index'))->assertRedirect(route('login'));
});

test('user with finance role can access invoices page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Comptable'));
    $this->actingAs($user)->get(route('invoices.index'))->assertOk();
});

test('invoices page shows invoice list', function () {
    $user = stubAdminUser();
    $this->actingAs($user);

    Livewire::test('pages::invoices.index')
        ->assertSee('Factures')
        ->assertSee('Total facturé');
});

test('user without finance role cannot access invoices page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Pâtissier'));
    $this->actingAs($user)->get(route('invoices.index'))->assertForbidden();
});

// ---------------------------------------------------------------------------
// Delivery
// ---------------------------------------------------------------------------

test('guest is redirected when accessing delivery page', function () {
    $this->get(route('delivery.index'))->assertRedirect(route('login'));
});

test('user with delivery access role can access delivery page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Chef Pâtissier'));
    $this->actingAs($user)->get(route('delivery.index'))->assertOk();
});

test('delivery page shows partners list', function () {
    $user = stubAdminUser();
    $this->actingAs($user);

    Livewire::test('pages::delivery.index')
        ->assertSee('Livreurs')
        ->assertSee('Partenaires de livraison');
});

test('user without delivery access cannot access delivery page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Comptable'));
    $this->actingAs($user)->get(route('delivery.index'))->assertForbidden();
});

// ---------------------------------------------------------------------------
// Production Calendar
// ---------------------------------------------------------------------------

test('guest is redirected when accessing production calendar page', function () {
    $this->get(route('production.calendar'))->assertRedirect(route('login'));
});

test('user with production access role can access calendar page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Chef Pâtissier'));
    $this->actingAs($user)->get(route('production.calendar'))->assertOk();
});

test('production calendar page shows planning', function () {
    $user = stubAdminUser();
    $this->actingAs($user);

    Livewire::test('pages::production.calendar')
        ->assertSee('Calendrier de Production')
        ->assertSee('Planning Hebdomadaire');
});

test('user without production access cannot access calendar page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Caissier'));
    $this->actingAs($user)->get(route('production.calendar'))->assertForbidden();
});

// ---------------------------------------------------------------------------
// Settings System
// ---------------------------------------------------------------------------

test('guest is redirected when accessing settings system page', function () {
    $this->get(route('settings.index'))->assertRedirect(route('login'));
});

test('user without manage-settings permission cannot access system page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Comptable'));
    $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
});

test('user with manage-settings can access system page', function () {
    $user = stubAdminUser();
    $this->actingAs($user)->get(route('settings.index'))->assertOk();
});

test('settings system page shows configuration form', function () {
    $user = stubAdminUser();
    $this->actingAs($user);

    Livewire::test('pages::settings.system')
        ->assertSee('Paramètres Système')
        ->assertSee('Configuration générale');
});


