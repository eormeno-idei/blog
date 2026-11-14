<?php

namespace App\Providers;

use App\Contracts\LogServiceInterface;
use App\Contracts\PostReelGenerationStatusInterface;
use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\Policies\PostPolicy;
use App\Repositories\Contracts\PostHistoryRepositoryInterface;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Repositories\Eloquent\PostHistoryRepository;
use App\Repositories\Eloquent\PostRepository;
use App\Services\Log\LogService;
use App\Services\Post\PostReelGenerationStatusService;
use App\Services\Post\PostService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            LogServiceInterface::class,
            LogService::class
        );

        // 1. Enlazar los repositorios de Eloquent con sus interfaces
        $this->app->singleton(
            PostRepositoryInterface::class,
            PostRepository::class
        );

        $this->app->singleton(
            PostHistoryRepositoryInterface::class,
            PostHistoryRepository::class
        );

        // 2. Enlazar el Servicio de Dominio
        $this->app->singleton(
            PostServiceInterface::class,
            PostService::class
        );

        // 3. Enlazar el Servicio de Estado de Generación de Reel
        $this->app->singleton(
            PostReelGenerationStatusInterface::class,
            PostReelGenerationStatusService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 3. Registrar explícitamente la Policy vinculada al DTO
        Gate::policy(PostDTO::class, PostPolicy::class);
    }
}
