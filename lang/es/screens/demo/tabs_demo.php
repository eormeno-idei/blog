<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Pestañas',
    'icon' => '🗂️',
    'title' => 'Contenedor con Tabs',
    'tabs' => [
        'overview' => [
            'label' => 'Resumen',
        ],
        'activity' => [
            'label' => 'Actividad',
        ],
        'settings' => [
            'label' => 'Configuracion',
        ],
        'advanced' => [
            'label' => 'Avanzado',
        ],
    ],
    'content' => [
        'overview_title' => 'Resumen general',
        'overview_body' => 'La tab de resumen usa asignacion por id y define el estado inicial del contenedor.',
        'activity_log' => 'La tab Actividad fue asociada usando su nombre visible, no el id interno.',
        'settings_copy' => 'Configuracion es closable. Al cerrarla, backend actualiza la definicion de tabs.',
        'advanced_info' => 'Esta tab no tiene colores definidos, por lo que usa los colores por defecto del tema mediante herencia CSS.',
    ],
    'toasts' => [
        'tab_closed' => 'Tab cerrada: :tab',
    ],
];
