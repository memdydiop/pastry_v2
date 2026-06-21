<?php

namespace App\Models;

use App\Enums\IngredientUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'unit',
        'stock_quantity',
        'alert_threshold',
        'is_critical',
        'notes',
    ];

    protected $casts = [
        'unit' => IngredientUnit::class,
        'stock_quantity' => 'decimal:2',
        'alert_threshold' => 'decimal:2',
        'is_critical' => 'boolean',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
