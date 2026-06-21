<?php

namespace App\Models;

use App\Enums\PlanFeature;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = [
        'name', 'slug', 'schema_name', 'plan_id',
        'status', 'trial_ends_at', 'subscription_ends_at', 'preferences',
    ];

    protected $casts = [
        'data' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'preferences' => 'json',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->latest('id')
            ->first();
    }

    public function canUse(PlanFeature $feature): bool
    {
        $subscription = $this->activeSubscription();
        if (!$subscription || !$subscription->plan) return false;
        return $subscription->plan->hasFeature($feature);
    }

    public function getLimit(string $key): ?int
    {
        $subscription = $this->activeSubscription();
        if (!$subscription || !$subscription->plan) return null;
        return $subscription->plan->getLimit($key);
    }
}
