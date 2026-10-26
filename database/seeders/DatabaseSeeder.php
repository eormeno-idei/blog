<?php

namespace Database\Seeders;

use App\Contracts\PostServiceInterface;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with sample posts for the USIM Post Manager tutorial.
     */
    public function run(): void
    {
        if (DB::table('posts')->count() > 0) {
            return;
        }

        $publisher = User::where('email', 'publisher@example.com')->first()
            ?? User::where('email', 'admin@example.com')->first()
            ?? User::first();

        $admin = User::where('email', 'admin@example.com')->first()
            ?? $publisher;

        if (! $publisher instanceof User || ! $admin instanceof User) {
            return;
        }

        /** @var PostServiceInterface $postService */
        $postService = app(PostServiceInterface::class);

        $postService->createPost([
            'user_id' => (int) $publisher->id,
            'title' => 'Cómo publicar contenido con UI::uploader y UI::image',
            'content' => 'En USIM, el componente UI::uploader().',
            'image' => '',
            'aspect_ratio' => '16:9',
            'background_color' => '#7c3aed',
            'text_color' => '#ffffff',
            'status' => 'published',
        ]);

        $postService->createPost([
            'user_id' => (int) $admin->id,
            'title' => 'Bienvenido al Gestor de Posts Didáctico con USIM',
            'content' => 'Esta aplicación muestra el flujo de roles.',
            'image' => '',
            'aspect_ratio' => '16:9',
            'background_color' => '#facc15',
            'text_color' => '#000000',
            'status' => 'published',
        ]);
    }
}
