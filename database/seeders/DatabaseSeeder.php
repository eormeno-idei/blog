<?php

namespace Database\Seeders;

use App\Contracts\PostServiceInterface;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

        if (!$publisher instanceof User || !$admin instanceof User) {
            return;
        }

        /** @var PostServiceInterface $postService */
        $postService = app(PostServiceInterface::class);

        $postService->createPost([
            'user_id' => (int) $publisher->id,
            'title' => 'Cómo publicar contenido con UI::uploader y UI::image',
            'content' => 'En USIM, el componente UI::uploader() sube los archivos de forma asíncrona a una carpeta temporal con vista previa inmediata, y al enviar el formulario se confirma con $this->post_image->confirm($params, \'images\'). Luego, UI::image() los renderiza de forma responsiva.',
            'image' => '',
            'aspect_ratio' => '16:9',
            'background_color' => '#7c3aed',
            'text_color' => '#ffffff',
            'status' => 'published',
        ]);

        $postService->createPost([
            'user_id' => (int) $admin->id,
            'title' => 'Bienvenido al Gestor de Posts Didáctico con USIM',
            'content' => 'Esta aplicación demuestra el flujo de roles en una única unidad organizacional (lobby): los invitados (guest) leen los últimos posts, los nuevos usuarios (registered) ven su pantalla de espera y editan su avatar, los publicadores (publisher) crean posts y los administradores (admin) moderan posts y asignan roles.',
            'image' => '',
            'aspect_ratio' => '16:9',
            'background_color' => '#facc15',
            'text_color' => '#000000',
            'status' => 'published',
        ]);
    }
}
