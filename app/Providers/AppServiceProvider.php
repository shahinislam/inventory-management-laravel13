<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Observers\AuditObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
        $this->registerAuditObservers();
    }

    /**
     * Attach the audit observer to the models worth keeping a history of.
     *
     * Deliberately explicit: ActivityLog itself must never be observed, and
     * high-volume rows like StockMovement already carry their own before/after
     * columns, so auditing them again would only duplicate data.
     */
    protected function registerAuditObservers(): void
    {
        $auditable = [
            Product::class,
            Category::class,
            Supplier::class,
            Customer::class,
            Warehouse::class,
            Invoice::class,
            PurchaseOrder::class,
            Promotion::class,
            User::class,
        ];

        foreach ($auditable as $model) {
            $model::observe(AuditObserver::class);
        }
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
