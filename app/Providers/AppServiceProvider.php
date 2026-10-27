<?php

namespace App\Providers;

use App\Contracts\LogServiceInterface;
use App\Contracts\PostServiceInterface;
use App\Services\Log\LogService;
use App\Services\Post\DatabasePostService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PostServiceInterface::class, DatabasePostService::class);
        $this->app->singleton(LogServiceInterface::class, LogService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
