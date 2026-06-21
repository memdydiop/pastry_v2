<?php

namespace App\Models;

use App\Enums\SupplierCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'contact_name',
        'phone',
        'email',
        'address',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'category' => SupplierCategory::class,
    ];

    public function incomingMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'supplier_id');
    }
}
