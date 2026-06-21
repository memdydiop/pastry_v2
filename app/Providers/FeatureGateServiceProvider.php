<?php

namespace App\Providers;

use App\Enums\PlanFeature;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class FeatureGateServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Blade::if('canuse', function (PlanFeature $feature) {
            $tenant = tenant();
            return $tenant && $tenant->canUse($feature);
        });

        Blade::if('cannotuse', function (PlanFeature $feature) {
            $tenant = tenant();
            return !$tenant || !$tenant->canUse($feature);
        });
    }
}
