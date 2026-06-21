<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'user_id',
    ];

    protected function fromStatus(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? OrderStatus::tryFrom($value) : null,
            set: fn ($value) => $value instanceof OrderStatus ? $value->value : $value,
        );
    }

    protected function toStatus(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => OrderStatus::tryFrom($value),
            set: fn ($value) => $value instanceof OrderStatus ? $value->value : $value,
        );
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
