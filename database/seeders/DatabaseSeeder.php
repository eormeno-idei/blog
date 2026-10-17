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

        Storage::disk('public')->makeDirectory('uploads/images');

        $svg1 = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450" viewBox="0 0 800 450">
  <defs>
    <linearGradient id="bg1" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#0f172a"/>
      <stop offset="100%" stop-color="#00d4aa"/>
    </linearGradient>
  </defs>
  <rect width="800" height="450" fill="url(#bg1)"/>
  <text x="50%" y="45%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-family="sans-serif" font-size="36" font-weight="bold">Bienvenido a USIM Post Manager</text>
  <text x="50%" y="58%" dominant-baseline="middle" text-anchor="middle" fill="#e2e8f0" font-family="sans-serif" font-size="20">Arquitectura Server-Driven UI 100% SPA en Laravel</text>
</svg>
SVG;

        $svg2 = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450" viewBox="0 0 800 450">
  <defs>
    <linearGradient id="bg2" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#1e1b4b"/>
      <stop offset="100%" stop-color="#3b82f6"/>
    </linearGradient>
  </defs>
  <rect width="800" height="450" fill="url(#bg2)"/>
  <text x="50%" y="45%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-family="sans-serif" font-size="34" font-weight="bold">Componentes UI::uploader() y UI::image()</text>
  <text x="50%" y="58%" dominant-baseline="middle" text-anchor="middle" fill="#cbd5e1" font-family="sans-serif" font-size="20">Gestión de archivos temporales y confirmación reactiva</text>
</svg>
SVG;

        Storage::disk('public')->put('uploads/images/sample-welcome.svg', $svg1);
        Storage::disk('public')->put('uploads/images/sample-components.svg', $svg2);

        /** @var PostServiceInterface $postService */
        $postService = app(PostServiceInterface::class);

        $postService->createPost([
            'user_id' => (int) $publisher->id,
            'title' => 'Cómo publicar contenido con UI::uploader y UI::image',
            'content' => 'En USIM, el componente UI::uploader() sube los archivos de forma asíncrona a una carpeta temporal con vista previa inmediata, y al enviar el formulario se confirma con $this->post_image->confirm($params, \'images\'). Luego, UI::image() los renderiza de forma responsiva.',
            'image' => 'sample-components.svg',
        ]);

        $postService->createPost([
            'user_id' => (int) $admin->id,
            'title' => 'Bienvenido al Gestor de Posts Didáctico con USIM',
            'content' => 'Esta aplicación demuestra el flujo de roles en una única unidad organizacional (lobby): los invitados (guest) leen los últimos posts, los nuevos usuarios (registered) ven su pantalla de espera y editan su avatar, los publicadores (publisher) crean posts y los administradores (admin) moderan posts y asignan roles.',
            'image' => 'sample-welcome.svg',
        ]);
    }
}
