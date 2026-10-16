<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Separadores',
    'icon' => '↔️',
    'title' => 'Demo de Contenedor Split',
    'intro' => 'Configura orientación, tamaño inicial y comportamiento de colapso. La barra del split es arrastrable.',
    'orientation' => [
        'label' => 'Orientación',
        'options' => [
            'horizontal' => 'Horizontal',
            'vertical' => 'Vertical',
        ],
    ],
    'split_size' => [
        'label' => 'Tamaño del primer panel (ej: 35% o 360px)',
        'placeholder' => '45%',
    ],
    'splitter_size' => [
        'label' => 'Tamaño de la barra divisora (ej: 8px)',
        'placeholder' => '8px',
    ],
    'draggable' => 'Arrastrable',
    'collapsible' => 'Colapsable',
    'collapse_target' => [
        'label' => 'Panel colapsable',
        'options' => [
            'first' => 'Primer panel',
            'second' => 'Segundo panel',
        ],
    ],
    'actions' => [
        'apply_sizes' => 'Aplicar medidas',
        'toggle_collapse' => 'Alternar colapso',
        'reset_demo' => 'Reiniciar demo',
        'collapse_first' => 'Colapsar panel A',
        'collapse_second' => 'Colapsar panel B',
    ],
    'panes' => [
        'first' => [
            'title' => 'Panel A',
            'description' => 'Este panel representa contenido principal.',
        ],
        'second' => [
            'title' => 'Panel B',
            'description' => 'Usa la barra central para redimensionar en vivo.',
        ],
    ],
];
