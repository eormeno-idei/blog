<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Selects',
    'icon' => '🖱️',
    'actions' => [
        'reset_all' => 'Reset All',
    ],
    'city' => [
        'label' => 'Select City',
        'placeholder' => [
            'choose_city' => 'Choose a city...',
            'select_country_first' => 'First select a country',
        ],
    ],
    'country' => [
        'label' => 'Select Country',
        'placeholder' => 'Choose a country...',
    ],
    'instruction' => 'Select a country to see available cities, then select a city to see details',
    'languages' => [
        'enable_multiple' => 'Enable multiple language selection',
        'label' => 'Select Language(s)',
        'placeholder' => [
            'multiple' => 'Choose language(s)...',
            'single' => 'Choose a language...',
            'up_to_three' => 'Choose up to 3 languages...',
        ],
        'search' => 'Search languages...',
    ],
    'result' => [
        'city_info' => [
            'header' => '📍 :city, :country
',
            'population' => '👥 Population: :population
',
            'timezone' => '🕐 Timezone: :timezone',
        ],
        'city_info_unavailable' => 'City information not available',
        'country_selected' => 'Country selected: :country. Now select a city.',
        'initial' => 'Select options above to see results',
        'reset_done' => 'All selections have been reset. Start over!',
        'select_city_to_continue' => 'Select a city to see details',
        'select_country_to_continue' => 'Select a country to continue',
    ],
    'title' => 'Select Component Demo',
];
