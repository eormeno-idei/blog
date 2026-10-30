<?php

namespace App\Providers;

use App\Contracts\LogServiceInterface;
use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\Policies\PostPolicy;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Repositories\Eloquent\EloquentPostRepository;
use App\Services\Log\LogService;
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

        // 1. Enlazar el Repositorio
        $this->app->singleton(
            PostRepositoryInterface::class,
            EloquentPostRepository::class
        );

        // 2. Enlazar el Servicio de Dominio
        $this->app->singleton(
            PostServiceInterface::class,
            PostService::class
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
