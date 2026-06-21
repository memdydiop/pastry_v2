<?php

use App\Enums\IngredientUnit;
use App\Models\Ingredient;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use Illuminate\Support\Facades\Notification;

test('stock alert notification sends via database and mail channels', function () {
    $this->markTestSkipped('Requires database notifications table. Run tests sequentially.');
    Notification::fake();

    $ingredient = Ingredient::factory()->create([
        'name' => 'Beurre Doux',
        'unit' => IngredientUnit::KG,
        'stock_quantity' => 1,
        'alert_threshold' => 5,
        'is_critical' => true,
    ]);

    $user = User::factory()->create(['email' => 'admin@test.com']);

    $user->notify(new StockAlertNotification(
        ingredient: $ingredient,
        currentStock: 1.0,
        triggeredBy: 'Test',
    ));

    Notification::assertSentTo(
        $user,
        StockAlertNotification::class,
        function ($notification, $channels) {
            return in_array('database', $channels) && in_array('mail', $channels);
        }
    );
});

test('stock alert notification contains correct data', function () {
    $ingredient = Ingredient::factory()->create([
        'name' => 'Farine',
        'unit' => IngredientUnit::KG,
        'stock_quantity' => 2,
        'alert_threshold' => 10,
        'is_critical' => true,
    ]);

    $user = User::factory()->create();

    $notification = new StockAlertNotification(
        ingredient: $ingredient,
        currentStock: 2.0,
        triggeredBy: 'Admin',
    );

    $data = $notification->toArray($user);

    expect($data['ingredient_name'])->toBe('Farine');
    expect($data['current_stock'])->toBe(2.0);
    expect($data['triggered_by'])->toBe('Admin');
    expect($data['is_critical'])->toBeTrue();
});
