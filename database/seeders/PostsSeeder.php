<?php

namespace Database\Seeders;

use App\Contracts\PostServiceInterface;
use Illuminate\Database\Seeder;

class PostsSeeder extends Seeder
{
    public function __construct(
        protected PostServiceInterface $postService
    ) {}

    public function run(): void
    {
        $posts = [
            [
                'user_id' => 1,
                'title' => 'Bienvenido a USIM Post Manager',
                'content' => 'Este es un ejemplo de post para mostrar cómo funciona la arquitectura Server-Driven UI en Laravel.',
                'image' => 'uploads/images/welcome.svg',
            ],
            [
                'user_id' => 1,
                'title' => 'Componentes UI::uploader() y UI::image()',
                'content' => 'Estos componentes permiten gestionar archivos temporales y confirmar la carga de imágenes de manera reactiva.',
                'image' => 'uploads/images/uploader.svg',
            ],
        ];

        foreach ($posts as $post) {
            $this->postService->createPost($post);
        }
    }
}
