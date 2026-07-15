<?php

namespace Webkul\Whatsapp\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Whatsapp\Console\Commands\NormalizePhones;

class WhatsappServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(Router $router): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'whatsapp');

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        // Public webhook endpoints (Meta calls these; no session/CSRF).
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        // Authenticated inbox endpoints, under the admin path.
        Route::middleware(['web', 'admin_locale', 'user'])
            ->prefix(config('app.admin_path'))
            ->group(__DIR__.'/../Routes/admin.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                NormalizePhones::class,
            ]);
        }
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/whatsapp.php',
            'whatsapp'
        );
    }
}
