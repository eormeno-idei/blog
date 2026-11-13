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
    'post_title' => 'Innovación Backend',
    'status_prefix' => 'ESTADO:',
    'status_badge' => 'BORRADOR',
    'history' => [
        'title' => 'Historial de versiones',
        'empty' => 'No hay eventos en el historial',
        'items' => [
            'v1_1' => [
                'label' => 'Versión 1.1',
                'badge' => 'En revisión',
                'subtitle' => 'Se agregó el logotipo de la facultad. · Basada en v1.0 · Generada por IA',
            ],
            'rev1' => [
                'label' => 'Solicitud de revisión',
                'badge' => 'Cambios solicitados',
                'subtitle' => 'Juan Pérez: «Agregá el logotipo de la facultad.»',
            ],
            'v1_0' => [
                'label' => 'Versión 1.0',
                'subtitle' => 'Borrador inicial generado',
            ],
        ],
    ],
    'waiting_title' => 'Esperando al creador',
    'waiting_desc' => 'El creador está editando el post. Recibirás una notificación cuando lo envíe a revisión.',
];
