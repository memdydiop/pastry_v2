<?php

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

if (! function_exists('recipeUser')) {
    function recipeUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Gérant/Admin'));

        return $user;
    }
}

test('recipe can be created with valid data', function () {
    $recipe = Recipe::factory()->create([
        'name' => 'Génoise vanille',
        'category' => 'Biscuit',
        'expected_cost' => 2500,
        'is_active' => true,
    ]);

    expect($recipe->name)->toBe('Génoise vanille');
    expect($recipe->category)->toBe('Biscuit');
    expect($recipe->expected_cost)->toEqual(2500.0);
    expect($recipe->is_active)->toBeTrue();
});

test('recipe can have ingredients', function () {
    $recipe = Recipe::factory()->create();
    $ingredient = Ingredient::factory()->create();

    $recipe->recipeIngredients()->create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 0.5,
    ]);

    expect($recipe->recipeIngredients)->toHaveCount(1);
    expect($recipe->recipeIngredients->first()->ingredient_id)->toBe($ingredient->id);
    expect($recipe->recipeIngredients->first()->quantity)->toEqual(0.5);
});

test('recipe ingredient has ingredient relationship', function () {
    $recipe = Recipe::factory()->create();
    $ingredient = Ingredient::factory()->create();

    $ri = $recipe->recipeIngredients()->create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 1.0,
    ]);

    expect($ri->ingredient->id)->toBe($ingredient->id);
    expect($ri->recipe->id)->toBe($recipe->id);
});

test('recipe has recipe ingredients count', function () {
    $recipe = Recipe::factory()->create();
    $ingredient1 = Ingredient::factory()->create();
    $ingredient2 = Ingredient::factory()->create();

    $recipe->recipeIngredients()->create(['ingredient_id' => $ingredient1->id, 'quantity' => 0.5]);
    $recipe->recipeIngredients()->create(['ingredient_id' => $ingredient2->id, 'quantity' => 1.0]);

    expect($recipe->fresh()->recipeIngredients()->count())->toBe(2);
});

test('deleting recipe cascades to its ingredients', function () {
    $recipe = Recipe::factory()->create();
    $ingredient = Ingredient::factory()->create();
    $recipe->recipeIngredients()->create(['ingredient_id' => $ingredient->id, 'quantity' => 0.5]);

    $recipe->delete();

    expect(Recipe::find($recipe->id))->toBeNull();
    expect(RecipeIngredient::where('recipe_id', $recipe->id)->exists())->toBeFalse();
});

test('guest is redirected when accessing recipe page', function () {
    $this->get(route('recipes.index'))->assertRedirect(route('login'));
});

test('user without role cannot access recipe page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('recipes.index'))->assertForbidden();
});

test('recipe page displays list of recipes', function () {
    $user = recipeUser();
    $this->actingAs($user);

    Recipe::factory()->create(['name' => 'Génoise vanille', 'is_active' => true]);
    Recipe::factory()->create(['name' => 'Crème au beurre', 'is_active' => true]);

    Livewire::test('pages::recipes.index')
        ->assertViewHas('totalRecipes', 2);
});

test('recipe page filters by search on name', function () {
    $user = recipeUser();
    $this->actingAs($user);

    Recipe::factory()->create(['name' => 'Génoise vanille', 'is_active' => true]);
    Recipe::factory()->create(['name' => 'Crème au beurre', 'is_active' => true]);

    Livewire::test('pages::recipes.index')
        ->set('search', 'Génoise')
        ->assertViewHas('recipes', fn ($p) => $p->total() === 1);
});

test('recipe page filters by search on category', function () {
    $user = recipeUser();
    $this->actingAs($user);

    Recipe::factory()->create(['name' => 'Génoise', 'category' => 'Biscuit', 'is_active' => true]);
    Recipe::factory()->create(['name' => 'Crème', 'category' => 'Garniture', 'is_active' => true]);

    Livewire::test('pages::recipes.index')
        ->set('search', 'Biscuit')
        ->assertViewHas('recipes', fn ($p) => $p->total() === 1);
});

test('recipe page hides inactive by default', function () {
    $user = recipeUser();
    $this->actingAs($user);

    Recipe::factory()->create(['name' => 'Active', 'is_active' => true]);
    Recipe::factory()->create(['name' => 'Inactive', 'is_active' => false]);

    Livewire::test('pages::recipes.index')
        ->assertViewHas('recipes', fn ($p) => $p->total() === 1);
});

test('recipe page shows inactive when toggled', function () {
    $user = recipeUser();
    $this->actingAs($user);

    Recipe::factory()->create(['name' => 'Active', 'is_active' => true]);
    Recipe::factory()->create(['name' => 'Inactive', 'is_active' => false]);

    Livewire::test('pages::recipes.index')
        ->set('showInactive', true)
        ->assertViewHas('recipes', fn ($p) => $p->total() === 2);
});

test('recipe can be created via modal', function () {
    $user = recipeUser();
    $this->actingAs($user);

    $ingredient = Ingredient::factory()->create(['name' => 'Farine']);

    Livewire::test('pages::recipes.index')
        ->call('openModal')
        ->set('name', 'Génoise test')
        ->set('category', 'Biscuit')
        ->set('recipeIngredients', [[
            'ingredient_id' => (string) $ingredient->id,
            'quantity' => '0.5',
            'unit_override' => '',
        ]])
        ->call('saveRecipe');

    expect(Recipe::where('name', 'Génoise test')->exists())->toBeTrue();
});

test('recipe creation requires name and ingredients', function () {
    $user = recipeUser();
    $this->actingAs($user);

    Livewire::test('pages::recipes.index')
        ->call('openModal')
        ->set('name', '')
        ->set('recipeIngredients', [['ingredient_id' => '', 'quantity' => '', 'unit_override' => '']])
        ->call('saveRecipe')
        ->assertHasErrors(['name', 'recipeIngredients.*.ingredient_id', 'recipeIngredients.*.quantity']);
});

test('recipe can be edited via modal', function () {
    $user = recipeUser();
    $this->actingAs($user);

    $ingredient = Ingredient::factory()->create(['name' => 'Farine']);
    $recipe = Recipe::factory()->create(['name' => 'Ancien nom']);

    Livewire::test('pages::recipes.index')
        ->call('openModal', $recipe->id)
        ->set('name', 'Nouveau nom')
        ->set('recipeIngredients', [[
            'ingredient_id' => (string) $ingredient->id,
            'quantity' => '1.0',
            'unit_override' => '',
        ]])
        ->call('saveRecipe');

    expect($recipe->fresh()->name)->toBe('Nouveau nom');
});

test('recipe edit replaces old ingredients', function () {
    $user = recipeUser();
    $this->actingAs($user);

    $oldIngredient = Ingredient::factory()->create(['name' => 'Ancien']);
    $newIngredient = Ingredient::factory()->create(['name' => 'Nouveau']);
    $recipe = Recipe::factory()->create();
    $recipe->recipeIngredients()->create(['ingredient_id' => $oldIngredient->id, 'quantity' => 0.5]);

    Livewire::test('pages::recipes.index')
        ->call('openModal', $recipe->id)
        ->set('recipeIngredients', [[
            'ingredient_id' => (string) $newIngredient->id,
            'quantity' => '2.0',
            'unit_override' => '',
        ]])
        ->call('saveRecipe');

    expect($recipe->fresh()->recipeIngredients)->toHaveCount(1);
    expect($recipe->fresh()->recipeIngredients->first()->ingredient_id)->toBe($newIngredient->id);
});

test('recipe can be deleted', function () {
    $user = recipeUser();
    $this->actingAs($user);

    $recipe = Recipe::factory()->create(['name' => 'À supprimer']);

    Livewire::test('pages::recipes.index')
        ->call('prepareDeleteRecipe', $recipe->id)
        ->call('confirmDeleteRecipe');

    expect(Recipe::find($recipe->id))->toBeNull();
});

test('Gérant/Admin can access recipe page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Gérant/Admin'));
    $this->actingAs($user);

    $this->get(route('recipes.index'))->assertOk();
});

test('Chef Pâtissier can access recipe page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Chef Pâtissier'));
    $this->actingAs($user);

    $this->get(route('recipes.index'))->assertOk();
});

test('Pâtissier cannot access recipe page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Pâtissier'));
    $this->actingAs($user);

    $this->get(route('recipes.index'))->assertForbidden();
});

test('Caissier cannot access recipe page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Caissier'));
    $this->actingAs($user);

    $this->get(route('recipes.index'))->assertForbidden();
});

test('Comptable cannot access recipe page', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Comptable'));
    $this->actingAs($user);

    $this->get(route('recipes.index'))->assertForbidden();
});
