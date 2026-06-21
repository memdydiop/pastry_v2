<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\User;
use App\Observers\OrderObserver;
use Carbon\CarbonImmutable;
use App\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMail();
        $this->configureRateLimiting();
        Gate::before(function (User $user, $ability) {
            return $user->hasRole('ghost') ? true : null;
        });
        Gate::define('gerantOrGhost', function (User $user) {
            return $user->hasAnyRole(['Gérant/Admin', 'ghost']);
        });
        Order::observe(OrderObserver::class);
    }

    protected function configureMail(): void
    {
        if (Schema::hasTable('settings')) {
            $fromAddress = Setting::getValue('mail_from_address');

            if ($fromAddress) {
                config()->set('mail.from.address', $fromAddress);
            }
        }
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->input('email').'|'.$request->ip()));
        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('transactions', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('stock', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
