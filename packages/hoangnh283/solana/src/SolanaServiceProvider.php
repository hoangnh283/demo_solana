<?php

namespace Hoangnh283\Solana;

use Illuminate\Support\ServiceProvider;
use Hoangnh283\Solana\Services\SolanaService;
use Hoangnh283\Solana\Console\Commands\ListenToSolanaDeposit;
use Illuminate\Support\Facades\Route;

class SolanaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->mergeConfigFrom(__DIR__ . '/../config/Solana.php', 'solana');

        $this->publishConfig();

        // $this->loadViewsFrom(__DIR__.'/resources/views', 'solana');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        // $this->registerRoutes();
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    private function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
        });
    }

    /**
    * Get route group configuration array.
    *
    * @return array
    */
    private function routeConfiguration()
    {
        return [
            'namespace'  => "Hoangnh283\Solana\Http\Controllers",
            'middleware' => 'api',
            'prefix'     => 'api'
        ];
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands([
            ListenToSolanaDeposit::class,
        ]);
        $this->app->bind(SolanaService::class, function ($app) {
            return new SolanaService();
        });
    }

    /**
     * Publish Config
     *
     * @return void
     */
    public function publishConfig()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/Solana.php' => config_path('Solana.php'),
            ], 'config');
        }
    }
}
