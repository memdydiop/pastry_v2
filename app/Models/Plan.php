<?php

namespace App\Models;

use App\Enums\PlanFeature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'price', 'currency',
        'interval', 'is_active', 'sort',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeatureValue::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasFeature(PlanFeature $feature): bool
    {
        $entry = $this->features->firstWhere('feature', $feature->value);
        if (!$entry) return false;
        return filter_var($entry->value, FILTER_VALIDATE_BOOLEAN);
    }

    public function getLimit(string $key): ?int
    {
        $entry = $this->features->firstWhere('feature', $key);
        if (!$entry) return null;
        $value = (int) $entry->value;
        return $value >= 0 ? $value : null;
    }
}
