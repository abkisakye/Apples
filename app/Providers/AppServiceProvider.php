<?php

namespace App\Providers;

use App\Models\BusinessSetting;
use App\Support\AccessService;
use App\Support\ApprovalPinService;
use App\Support\StockAvailabilityService;
use App\Support\StoreAssignmentService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AccessService::class, fn ($app) => new AccessService($app['request']));
        $this->app->scoped(ApprovalPinService::class, fn () => new ApprovalPinService());
        $this->app->scoped(StoreAssignmentService::class, fn () => new StoreAssignmentService());
        $this->app->scoped(StockAvailabilityService::class, fn () => new StockAvailabilityService());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Paginator::defaultView('vendor.pagination.default');
        Paginator::defaultSimpleView('vendor.pagination.simple-default');
        $this->loadBusinessSettings();

        View::composer('*', function ($view): void {
            $this->loadBusinessSettings();
            $view->with('access', app(AccessService::class));
        });
    }

    private function loadBusinessSettings(): void
    {
        try {
            if (! Schema::hasTable('business_settings')) {
                return;
            }

            $settings = BusinessSetting::query()->pluck('value', 'key')->all();

            foreach ([
                'name',
                'tagline',
                'address',
                'phone',
                'email',
                'tin',
                'logo_url',
                'currency',
                'receipt_footer',
                'invoice_footer',
                'statement_footer',
                'admin_approval_pin',
                'cashier_discount_limit',
            ] as $key) {
                if (array_key_exists($key, $settings)) {
                    config(["business.{$key}" => $settings[$key]]);
                }
            }
        } catch (Throwable) {
            // Fall back to file/env config when the settings table is not available yet.
        }
    }
}
