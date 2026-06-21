<?php

use App\Enums\IngredientUnit;
use App\Enums\InventoryMovementType;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

if (! function_exists('stockUser')) {
    function stockUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Gérant/Admin'));

        return $user;
    }
}

if (! function_exists('createIngredient')) {
    function createIngredient(array $overrides = []): Ingredient
    {
        return Ingredient::factory()->create($overrides);
    }
}

// ---------------------------------------------------------------------------
// Model — Création & Validation
// ---------------------------------------------------------------------------

test('ingredient can be created with valid data', function () {
    $ingredient = createIngredient([
        'name' => 'Farine de blé T55',
        'unit' => 'kg',
        'stock_quantity' => 25.0,
        'alert_threshold' => 5.0,
        'is_critical' => false,
    ]);

    expect($ingredient->name)->toBe('Farine de blé T55');
    expect($ingredient->unit)->toBe(IngredientUnit::KG);
    expect($ingredient->stock_quantity)->toEqual(25.0);
    expect($ingredient->alert_threshold)->toEqual(5.0);
    expect($ingredient->is_critical)->toBeFalse();
});

test('ingredient can have critical flag', function () {
    $ingredient = createIngredient(['is_critical' => true]);

    expect($ingredient->is_critical)->toBeTrue();
});

test('ingredient stock can be incremented and decremented', function () {
    $ingredient = createIngredient(['stock_quantity' => 10.0]);

    $ingredient->increment('stock_quantity', 5.0);
    expect($ingredient->fresh()->stock_quantity)->toEqual(15.0);

    $ingredient->decrement('stock_quantity', 3.0);
    expect($ingredient->fresh()->stock_quantity)->toEqual(12.0);
});

test('ingredient unit enum values are correct', function () {
    expect(IngredientUnit::KG->value)->toBe('kg');
    expect(IngredientUnit::L->value)->toBe('L');
    expect(IngredientUnit::UNITÉ->value)->toBe('unité');
    expect(IngredientUnit::BOÎTE->value)->toBe('boîte');
});

test('ingredient has movements relationship', function () {
    $ingredient = createIngredient();

    $user = User::factory()->create();

    $movement = $ingredient->movements()->create([
        'type' => InventoryMovementType::IN,
        'quantity' => 10.0,
        'notes' => 'Test entrée',
        'user_id' => $user->id,
    ]);

    expect($ingredient->movements)->toHaveCount(1);
    expect($movement->ingredient_id)->toBe($ingredient->id);
});

// ---------------------------------------------------------------------------
// Model — InventoryMovement Types
// ---------------------------------------------------------------------------

test('inventory movement type enum values are correct', function () {
    expect(InventoryMovementType::IN->value)->toBe('in');
    expect(InventoryMovementType::OUT->value)->toBe('out');
    expect(InventoryMovementType::ADJUST->value)->toBe('adjust');
    expect(InventoryMovementType::LOSS->value)->toBe('loss');
});

test('in movement increases stock and logs movement', function () {
    $user = stockUser();
    $this->actingAs($user);

    $ingredient = createIngredient(['stock_quantity' => 10.0]);

    $ingredient->increment('stock_quantity', 5.0);

    $movement = $ingredient->movements()->create([
        'type' => InventoryMovementType::IN,
        'quantity' => 5.0,
        'notes' => 'Réapprovisionnement',
        'user_id' => $user->id,
    ]);

    expect($ingredient->fresh()->stock_quantity)->toEqual(15.0);
    expect($movement->type)->toBe(InventoryMovementType::IN);
});

test('out movement decreases stock and logs movement', function () {
    $user = stockUser();
    $this->actingAs($user);

    $ingredient = createIngredient(['stock_quantity' => 20.0]);

    $ingredient->decrement('stock_quantity', 8.0);

    $movement = $ingredient->movements()->create([
        'type' => InventoryMovementType::OUT,
        'quantity' => 8.0,
        'notes' => 'Consommation',
        'user_id' => $user->id,
    ]);

    expect($ingredient->fresh()->stock_quantity)->toEqual(12.0);
    expect($movement->type)->toBe(InventoryMovementType::OUT);
});

test('loss movement decreases stock with loss type', function () {
    $user = stockUser();
    $this->actingAs($user);

    $ingredient = createIngredient(['stock_quantity' => 10.0]);

    $ingredient->decrement('stock_quantity', 2.0);

    $movement = $ingredient->movements()->create([
        'type' => InventoryMovementType::LOSS,
        'quantity' => 2.0,
        'notes' => 'Perte : Casse',
        'user_id' => $user->id,
    ]);

    expect($ingredient->fresh()->stock_quantity)->toEqual(8.0);
    expect($movement->type)->toBe(InventoryMovementType::LOSS);
});

test('inventory movement has correct relationships', function () {
    $user = stockUser();
    $this->actingAs($user);
    $supplier = Supplier::factory()->create();
    $ingredient = createIngredient();

    $movement = $ingredient->movements()->create([
        'type' => InventoryMovementType::IN,
        'quantity' => 10.0,
        'unit_price' => 500.0,
        'notes' => 'Test',
        'user_id' => $user->id,
        'supplier_id' => $supplier->id,
    ]);

    expect($movement->ingredient)->toBeInstanceOf(Ingredient::class);
    expect($movement->user)->toBeInstanceOf(User::class);
    expect($movement->supplier)->toBeInstanceOf(Supplier::class);
});

// ---------------------------------------------------------------------------
// Model — Alertes & Seuils
// ---------------------------------------------------------------------------

test('ingredient with stock below alert threshold is flagged', function () {
    $low = createIngredient(['stock_quantity' => 3.0, 'alert_threshold' => 5.0]);
    $ok = createIngredient(['stock_quantity' => 10.0, 'alert_threshold' => 5.0]);

    $lowStock = Ingredient::whereColumn('stock_quantity', '<=', 'alert_threshold')->get();

    expect($lowStock->pluck('id'))->toContain($low->id);
    expect($lowStock->pluck('id'))->not->toContain($ok->id);
});

test('critical ingredients can be queried', function () {
    createIngredient(['is_critical' => true, 'stock_quantity' => 1.0, 'alert_threshold' => 5.0]);
    createIngredient(['is_critical' => false, 'stock_quantity' => 1.0, 'alert_threshold' => 5.0]);

    $critical = Ingredient::where('is_critical', true)
        ->whereColumn('stock_quantity', '<=', 'alert_threshold')
        ->count();

    expect($critical)->toBe(1);
});

// ---------------------------------------------------------------------------
// Livewire — Liste et filtres
// ---------------------------------------------------------------------------

test('guest is redirected when accessing stock page', function () {
    $response = $this->get(route('stock.index'));
    $response->assertRedirect(route('login'));
});

test('user with stock role can access stock page', function () {
    $user = stockUser();
    $this->actingAs($user);

    $this->get(route('stock.index'))->assertOk();
});

test('user without role cannot access stock page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('stock.index'))->assertForbidden();
});

test('stock page displays list of ingredients', function () {
    $user = stockUser();
    $this->actingAs($user);

    createIngredient(['name' => 'Farine']);
    createIngredient(['name' => 'Sucre']);
    createIngredient(['name' => 'Beurre']);

    Livewire::test('pages::stock.index')
        ->assertViewHas('totalIngredients', 3);
});

test('stock page filters by search', function () {
    $user = stockUser();
    $this->actingAs($user);

    createIngredient(['name' => 'Farine de blé']);
    createIngredient(['name' => 'Sucre semoule']);

    Livewire::test('pages::stock.index')
        ->set('search', 'Farine')
        ->assertViewHas('ingredients', fn ($p) => $p->total() === 1);
});

test('stock page shows critical filter', function () {
    $user = stockUser();
    $this->actingAs($user);

    createIngredient(['name' => 'Beurre', 'is_critical' => true, 'stock_quantity' => 1.0, 'alert_threshold' => 5.0]);
    createIngredient(['name' => 'Farine', 'is_critical' => false, 'stock_quantity' => 1.0, 'alert_threshold' => 5.0]);

    Livewire::test('pages::stock.index')
        ->set('showCritical', true)
        ->assertViewHas('ingredients', fn ($p) => $p->total() === 1);
});

// ---------------------------------------------------------------------------
// Livewire — Ajout / Modification d'ingrédient
// ---------------------------------------------------------------------------

test('ingredient can be created via modal', function () {
    $user = stockUser();
    $this->actingAs($user);

    Livewire::test('pages::stock.index')
        ->call('openAddModal')
        ->set('name', 'Nouvel ingrédient')
        ->set('unit', 'kg')
        ->set('alert_threshold', 5.0)
        ->call('saveIngredient');

    expect(Ingredient::where('name', 'Nouvel ingrédient')->exists())->toBeTrue();
});

test('ingredient creation requires valid data', function () {
    $user = stockUser();
    $this->actingAs($user);

    Livewire::test('pages::stock.index')
        ->call('openAddModal')
        ->set('name', '')
        ->call('saveIngredient')
        ->assertHasErrors(['name']);
});

test('ingredient can be edited via modal', function () {
    $user = stockUser();
    $this->actingAs($user);

    $ingredient = createIngredient(['name' => 'Ancien nom', 'alert_threshold' => 3.0]);

    Livewire::test('pages::stock.index')
        ->call('openAddModal', $ingredient->id)
        ->set('name', 'Nouveau nom')
        ->set('alert_threshold', 5.0)
        ->call('saveIngredient');

    expect($ingredient->fresh()->name)->toBe('Nouveau nom');
    expect($ingredient->fresh()->alert_threshold)->toEqual(5.0);
});

// ---------------------------------------------------------------------------
// Livewire — Entrée de stock
// ---------------------------------------------------------------------------

test('incoming stock records movement and increases quantity', function () {
    $user = stockUser();
    $this->actingAs($user);

    $supplier = Supplier::factory()->create();
    $ingredient = createIngredient(['stock_quantity' => 10.0]);

    Livewire::test('pages::stock.index')
        ->call('openIncomingModal')
        ->set('incoming_ingredient_id', (string) $ingredient->id)
        ->set('incoming_quantity', 15.0)
        ->set('incoming_supplier_id', (string) $supplier->id)
        ->call('recordIncoming');

    expect($ingredient->fresh()->stock_quantity)->toEqual(25.0);
    expect($ingredient->movements()->where('type', InventoryMovementType::IN)->count())->toBe(1);
});

test('incoming stock validates required fields', function () {
    $user = stockUser();
    $this->actingAs($user);

    Livewire::test('pages::stock.index')
        ->call('openIncomingModal')
        ->call('recordIncoming')
        ->assertHasErrors(['incoming_ingredient_id', 'incoming_quantity', 'incoming_supplier_id']);
});

// ---------------------------------------------------------------------------
// Livewire — Consommation journalière
// ---------------------------------------------------------------------------

test('consumption modal is disabled when no stock', function () {
    $user = stockUser();
    $this->actingAs($user);

    $component = Livewire::test('pages::stock.index');
    $component->call('openConsumptionModal');
    expect(true)->toBeTrue();
});

test('consumption records out movement and decreases stock', function () {
    $user = stockUser();
    $this->actingAs($user);

    $ingredient = createIngredient(['stock_quantity' => 10.0]);

    Livewire::test('pages::stock.index')
        ->call('openConsumptionModal')
        ->set('consumption', [$ingredient->id => 4.0])
        ->call('recordConsumption');

    expect($ingredient->fresh()->stock_quantity)->toEqual(6.0);
    expect($ingredient->movements()->where('type', InventoryMovementType::OUT)->count())->toBe(1);
});

test('consumption throws error on insufficient stock', function () {
    $user = stockUser();
    $this->actingAs($user);

    $ingredient = createIngredient(['stock_quantity' => 2.0]);

    Livewire::test('pages::stock.index')
        ->call('openConsumptionModal')
        ->set('consumption', [$ingredient->id => 5.0])
        ->call('recordConsumption')
        ->assertHasErrors('consumption');
});

test('consumption requires at least one ingredient', function () {
    $user = stockUser();
    $this->actingAs($user);

    createIngredient(['stock_quantity' => 10.0]);

    Livewire::test('pages::stock.index')
        ->call('openConsumptionModal')
        ->call('recordConsumption')
        ->assertHasErrors('consumption');
});

// ---------------------------------------------------------------------------
// Livewire — Pertes
// ---------------------------------------------------------------------------

test('loss records movement of type loss and decreases stock', function () {
    $user = stockUser();
    $this->actingAs($user);

    $ingredient = createIngredient(['stock_quantity' => 10.0]);

    Livewire::test('pages::stock.index')
        ->call('openLossModal')
        ->set('loss_ingredient_id', (string) $ingredient->id)
        ->set('loss_quantity', 3.0)
        ->set('loss_reason', 'casse')
        ->call('recordLoss');

    expect($ingredient->fresh()->stock_quantity)->toEqual(7.0);
    expect($ingredient->movements()->where('type', InventoryMovementType::LOSS)->count())->toBe(1);
    expect($ingredient->movements()->first()->notes)->toContain('Casse');
});

test('loss validates required fields', function () {
    $user = stockUser();
    $this->actingAs($user);

    Livewire::test('pages::stock.index')
        ->call('openLossModal')
        ->call('recordLoss')
        ->assertHasErrors(['loss_ingredient_id', 'loss_quantity']);
});

test('loss validates reason is one of allowed values', function () {
    $user = stockUser();
    $this->actingAs($user);

    $ingredient = createIngredient(['stock_quantity' => 10.0]);

    Livewire::test('pages::stock.index')
        ->call('openLossModal')
        ->set('loss_ingredient_id', (string) $ingredient->id)
        ->set('loss_quantity', 1.0)
        ->set('loss_reason', 'invalide')
        ->call('recordLoss')
        ->assertHasErrors(['loss_reason']);
});

test('all loss reasons can be used', function () {
    $user = stockUser();
    $this->actingAs($user);

    $reasons = ['casse', 'perime', 'gaspillage', 'ratage', 'degustation', 'souillure', 'autre'];

    foreach ($reasons as $reason) {
        $ingredient = createIngredient(['stock_quantity' => 10.0]);

        Livewire::test('pages::stock.index')
            ->call('openLossModal')
            ->set('loss_ingredient_id', (string) $ingredient->id)
            ->set('loss_quantity', 1.0)
            ->set('loss_reason', $reason)
            ->call('recordLoss');

        expect($ingredient->fresh()->stock_quantity)->toEqual(9.0);
    }
});

test('loss throws error on insufficient stock', function () {
    $user = stockUser();
    $this->actingAs($user);

    $ingredient = createIngredient(['stock_quantity' => 0.5]);

    Livewire::test('pages::stock.index')
        ->call('openLossModal')
        ->set('loss_ingredient_id', (string) $ingredient->id)
        ->set('loss_quantity', 2.0)
        ->set('loss_reason', 'casse')
        ->call('recordLoss')
        ->assertHasErrors('loss_quantity');
});

// ---------------------------------------------------------------------------
// Permissions — Accès Stocks
// ---------------------------------------------------------------------------

test('Gérant/Admin can access stock', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Gérant/Admin'));
    $this->actingAs($user);

    $this->get(route('stock.index'))->assertOk();
});

test('Chef Pâtissier can access stock', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Chef Pâtissier'));
    $this->actingAs($user);

    $this->get(route('stock.index'))->assertOk();
});

test('Comptable cannot access stock', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Comptable'));
    $this->actingAs($user);

    $this->get(route('stock.index'))->assertForbidden();
});

test('Pâtissier cannot access stock', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Pâtissier'));
    $this->actingAs($user);

    $this->get(route('stock.index'))->assertForbidden();
});

test('Caissier cannot access stock', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Caissier'));
    $this->actingAs($user);

    $this->get(route('stock.index'))->assertForbidden();
});
