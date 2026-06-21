<?php

use App\Enums\IngredientUnit;
use App\Mail\StockAlertMail;
use App\Models\Ingredient;

test('stock alert mail has correct subject and content', function () {
    $ingredient = Ingredient::factory()->create([
        'name' => 'Beurre Doux',
        'unit' => IngredientUnit::KG,
        'stock_quantity' => 1,
        'alert_threshold' => 5,
        'is_critical' => true,
    ]);

    $mailable = new StockAlertMail(
        ingredient: $ingredient,
        currentStock: 1.0,
        triggeredBy: 'Système',
    );

    $mailable->assertHasSubject('Alerte Stock Critique : Beurre Doux');
    $mailable->assertSeeInHtml('Beurre Doux');
    $mailable->assertSeeInHtml('1,00');
    $mailable->assertSeeInHtml('5,00');
    $mailable->assertSeeInHtml('Système');
});
