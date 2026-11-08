<?php

namespace Database\Seeders;

use App\Contracts\PostServiceInterface;
use App\Models\User;
use Idei\Usim\Support\UsimConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function __construct(
        protected UsimConfig $usimConfig,
        protected PostServiceInterface $postService
    ) {}

    /**
     * Seed the application's database with sample posts for the USIM Post Manager tutorial.
     */
    public function run(): void
    {
        if (DB::table('posts')->count() > 0) {
            return;
        }

        $rootUser = $this->usimConfig->rootUser;

        $root = User::where('email', $rootUser->email)->first();

        $publisher = User::where('email', 'publisher@example.com')->first()
            ?? User::where('email', 'admin@example.com')->first()
            ?? User::first();

        $admin = User::where('email', 'admin@example.com')->first()
            ?? $publisher;

        if (! $publisher instanceof User || ! $admin instanceof User) {
            return;
        }

        $this->postService->createPost([
            'user_id' => (int) $publisher->id,
            'title' => 'Cómo publicar contenido con UI::uploader y UI::image',
            'content' => 'En USIM, el componente UI::uploader().',
            'image' => '',
            'aspect_ratio' => '16:9',
            'status' => 'published',
        ]);

        $this->postService->createPost([
            'user_id' => $root->id,
            'title' => 'Curso de USIM: Gestión de archivos temporales y carga de imágenes',
            'content' => 'En este curso, aprenderás a gestionar archivos temporales y a cargar imágenes de manera reactiva utilizando los componentes UI::uploader() y UI::image() en USIM.',
            'image' => '',
            'aspect_ratio' => '16:9',
            'from' => now()->addDays(1),
            'to' => now()->addDays(7),
            'status' => 'draft',
        ]);

        $this->postService->createPost([
            'user_id' => $admin->id,
            'title' => 'Bienvenido al Gestor de Posts Didáctico con USIM',
            'content' => 'Esta aplicación muestra el flujo de roles.',
            'image' => '',
            'aspect_ratio' => '16:9',
            'status' => 'published',
        ]);
    }
}
