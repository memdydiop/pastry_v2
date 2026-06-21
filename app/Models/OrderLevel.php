<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'recipe_id',
        'level_number',
        'shape',
        'flavor_biscuit',
        'flavor_cream',
        'filling',
        'diameter_cm',
        'width_cm',
        'length_cm',
        'height_cm',
        'notes',
    ];

    protected $casts = [
        'level_number' => 'integer',
        'diameter_cm' => 'decimal:1',
        'width_cm' => 'decimal:1',
        'length_cm' => 'decimal:1',
        'height_cm' => 'decimal:1',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
