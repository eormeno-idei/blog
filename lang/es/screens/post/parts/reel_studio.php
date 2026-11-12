<?php

return [
    'menu_title' => 'Reel Studio',
    'icon' => '',
    'actions' => [
        'generate_first_structure' => 'Generar Primera Estructura',
    ],
    'context_received' => [
        'title' => '✅ Contexto recibido',
        'content' => '✅ Texto Markdown cargado',
        'images' => '✅ Imágenes adjuntas',
    ],
    'initial_instructions' => [
        'placeholder' => 'Agrega instrucciones adicionales para el primer borrador (Opcional)',
    ],
    'toast' => [
        'post_not_found' => 'No se encontró el post.',
        'generating_first_structure' => 'Generando primera estructura de guion con IA...',
        'unknown_error' => 'Error desconocido',
        'generation_failed' => 'Error al generar la estructura: :message',
    ],
];
