<?php

namespace App\Models;

use App\Enums\ClientGender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'gender',
        'notes',
    ];

    protected $casts = [
        'gender' => ClientGender::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Historique des commandes passées par ce client
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
