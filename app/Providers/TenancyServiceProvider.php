<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Listeners\BootstrapTenancy;
use Stancl\Tenancy\Listeners\RevertToCentralContext;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->bootEvents();
    }

    protected function bootEvents(): void
    {
        Event::listen(TenancyInitialized::class, BootstrapTenancy::class);
        Event::listen(TenancyEnded::class, RevertToCentralContext::class);

        Event::listen(TenancyInitialized::class, function (TenancyInitialized $event) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($event->tenancy->tenant->id);
        });

        Event::listen(TenancyEnded::class, function () {
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        });
    }
}
