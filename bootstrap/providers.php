<?php

use App\Providers\AppServiceProvider;
use App\Providers\FeatureGateServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    FeatureGateServiceProvider::class,
    TenancyServiceProvider::class,
];
