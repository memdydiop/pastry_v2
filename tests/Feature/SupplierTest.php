<?php

use App\Enums\InventoryMovementType;
use App\Enums\SupplierCategory;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

if (! function_exists('supplierUser')) {
    function supplierUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Gérant/Admin'));

        return $user;
    }
}

if (! function_exists('createSupplier')) {
    function createSupplier(array $overrides = []): Supplier
    {
        return Supplier::factory()->create($overrides);
    }
}

test('supplier can be created with valid data', function () {
    $supplier = createSupplier([
        'name' => 'Auchan Cap Sud',
        'category' => SupplierCategory::SUPERMARCHÉ,
        'phone' => '+225 01 02 03 04 05',
        'is_active' => true,
    ]);

    expect($supplier->name)->toBe('Auchan Cap Sud');
    expect($supplier->category)->toBe(SupplierCategory::SUPERMARCHÉ);
    expect($supplier->phone)->toBe('+225 01 02 03 04 05');
    expect($supplier->is_active)->toBeTrue();
});

test('guest is redirected when accessing supplier page', function () {
    $this->get(route('suppliers.index'))->assertRedirect(route('login'));
});

test('user without role cannot access supplier page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('suppliers.index'))->assertForbidden();
});

test('supplier page displays list of suppliers', function () {
    $user = supplierUser();
    $this->actingAs($user);

    createSupplier(['name' => 'Auchan']);
    createSupplier(['name' => 'Marché de Treichville']);

    Livewire::test('pages::suppliers.index')
        ->assertViewHas('totalSuppliers', 2);
});

test('supplier page filters by search on name', function () {
    $user = supplierUser();
    $this->actingAs($user);

    createSupplier(['name' => 'Auchan Cap Sud']);
    createSupplier(['name' => 'Marché de Treichville']);

    Livewire::test('pages::suppliers.index')
        ->set('search', 'Auchan')
        ->assertViewHas('suppliers', fn ($p) => $p->total() === 1);
});

test('supplier page filters by search on contact name', function () {
    $user = supplierUser();
    $this->actingAs($user);

    createSupplier(['name' => 'Fournisseur A', 'contact_name' => 'Kouamé']);
    createSupplier(['name' => 'Fournisseur B', 'contact_name' => 'Fatou']);

    Livewire::test('pages::suppliers.index')
        ->set('search', 'Kouamé')
        ->assertViewHas('suppliers', fn ($p) => $p->total() === 1);
});

test('supplier page filters by category', function () {
    $user = supplierUser();
    $this->actingAs($user);

    createSupplier(['name' => 'Auchan', 'category' => SupplierCategory::SUPERMARCHÉ]);
    createSupplier(['name' => 'Marché', 'category' => SupplierCategory::MARCHÉ]);

    Livewire::test('pages::suppliers.index')
        ->set('categoryFilter', SupplierCategory::SUPERMARCHÉ->value)
        ->assertViewHas('suppliers', fn ($p) => $p->total() === 1);
});

test('supplier page hides inactive by default', function () {
    $user = supplierUser();
    $this->actingAs($user);

    createSupplier(['name' => 'Actif', 'is_active' => true]);
    createSupplier(['name' => 'Inactif', 'is_active' => false]);

    Livewire::test('pages::suppliers.index')
        ->assertViewHas('suppliers', fn ($p) => $p->total() === 1);
});

test('supplier page shows inactive when toggled', function () {
    $user = supplierUser();
    $this->actingAs($user);

    createSupplier(['name' => 'Actif', 'is_active' => true]);
    createSupplier(['name' => 'Inactif', 'is_active' => false]);

    Livewire::test('pages::suppliers.index')
        ->set('showInactive', true)
        ->assertViewHas('suppliers', fn ($p) => $p->total() === 2);
});

test('supplier can be created via modal', function () {
    $user = supplierUser();
    $this->actingAs($user);

    Livewire::test('pages::suppliers.index')
        ->call('openModal')
        ->set('name', 'Nouveau fournisseur')
        ->set('category', SupplierCategory::GROSSISTE->value)
        ->set('phone', '+225 01 02 03 04')
        ->call('save');

    expect(Supplier::where('name', 'Nouveau fournisseur')->exists())->toBeTrue();
});

test('supplier creation requires name, category and phone', function () {
    $user = supplierUser();
    $this->actingAs($user);

    Livewire::test('pages::suppliers.index')
        ->call('openModal')
        ->set('name', '')
        ->set('category', '')
        ->set('phone', '')
        ->call('save')
        ->assertHasErrors(['name', 'category', 'phone']);
});

test('supplier must have valid category value', function () {
    $user = supplierUser();
    $this->actingAs($user);

    Livewire::test('pages::suppliers.index')
        ->call('openModal')
        ->set('name', 'Test')
        ->set('category', 'invalid')
        ->set('phone', '+225 01 02 03')
        ->call('save')
        ->assertHasErrors(['category']);
});

test('supplier can be edited via modal', function () {
    $user = supplierUser();
    $this->actingAs($user);

    $supplier = createSupplier(['name' => 'Ancien nom', 'category' => SupplierCategory::FOURNISSEUR]);

    Livewire::test('pages::suppliers.index')
        ->call('openModal', $supplier->id)
        ->set('name', 'Nouveau nom')
        ->call('save');

    expect($supplier->fresh()->name)->toBe('Nouveau nom');
});

test('supplier can be deleted', function () {
    $user = supplierUser();
    $this->actingAs($user);

    $supplier = createSupplier(['name' => 'À supprimer']);

    Livewire::test('pages::suppliers.index')
        ->call('prepareDelete', $supplier->id)
        ->call('confirmDelete');

    expect(Supplier::find($supplier->id))->toBeNull();
});

test('supplier with incoming movements is deactivated instead of deleted', function () {
    $user = supplierUser();
    $this->actingAs($user);

    $supplier = createSupplier(['name' => 'Fournisseur test', 'is_active' => true]);
    $ingredient = Ingredient::factory()->create();

    InventoryMovement::create([
        'supplier_id' => $supplier->id,
        'ingredient_id' => $ingredient->id,
        'type' => InventoryMovementType::IN,
        'quantity' => 10,
        'unit_price' => 100,
        'notes' => 'Test movement',
        'user_id' => $user->id,
    ]);

    Livewire::test('pages::suppliers.index')
        ->call('prepareDelete', $supplier->id)
        ->call('confirmDelete');

    expect($supplier->fresh()->is_active)->toBeFalse();
    expect(Supplier::find($supplier->id))->not->toBeNull();
});

test('Gérant/Admin can access supplier page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Gérant/Admin'));
    $this->actingAs($user);

    $this->get(route('suppliers.index'))->assertOk();
});

test('Chef Pâtissier can access supplier page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Chef Pâtissier'));
    $this->actingAs($user);

    $this->get(route('suppliers.index'))->assertOk();
});

test('Pâtissier cannot access supplier page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Pâtissier'));
    $this->actingAs($user);

    $this->get(route('suppliers.index'))->assertForbidden();
});

test('Caissier cannot access supplier page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Caissier'));
    $this->actingAs($user);

    $this->get(route('suppliers.index'))->assertForbidden();
});

test('Comptable cannot access supplier page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Comptable'));
    $this->actingAs($user);

    $this->get(route('suppliers.index'))->assertForbidden();
});

test('supplier page exposes categories to view', function () {
    $user = supplierUser();
    $this->actingAs($user);

    Livewire::test('pages::suppliers.index')
        ->assertViewHas('categories');
});
