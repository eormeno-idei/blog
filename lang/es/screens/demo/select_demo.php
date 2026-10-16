<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Selectores',
    'icon' => '🖱️',
    'actions' => [
        'reset_all' => 'Reiniciar Todo',
    ],
    'city' => [
        'label' => 'Seleccionar Ciudad',
        'placeholder' => [
            'choose_city' => 'Elige una ciudad...',
            'select_country_first' => 'Primero selecciona un país',
        ],
    ],
    'country' => [
        'label' => 'Seleccionar País',
        'placeholder' => 'Elige un país...',
    ],
    'instruction' => 'Selecciona un país para ver ciudades disponibles, luego selecciona una ciudad para ver detalles',
    'languages' => [
        'enable_multiple' => 'Habilitar selección múltiple de idiomas',
        'label' => 'Seleccionar Idioma(s)',
        'placeholder' => [
            'multiple' => 'Elige idioma(s)...',
            'single' => 'Elige un idioma...',
            'up_to_three' => 'Elige hasta 3 idiomas...',
        ],
        'search' => 'Buscar idiomas...',
    ],
    'result' => [
        'city_info' => [
            'header' => '📍 :city, :country
',
            'population' => '👥 Población: :population
',
            'timezone' => '🕐 Zona horaria: :timezone',
        ],
        'city_info_unavailable' => 'Información de ciudad no disponible',
        'country_selected' => 'País seleccionado: :country. Ahora selecciona una ciudad.',
        'initial' => 'Selecciona opciones arriba para ver resultados',
        'reset_done' => 'Todas las selecciones se reiniciaron. Comienza de nuevo!',
        'select_city_to_continue' => 'Selecciona una ciudad para ver detalles',
        'select_country_to_continue' => 'Selecciona un país para continuar',
    ],
    'title' => 'Demo de Componente Select',
];
