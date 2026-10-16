<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Tabs',
    'icon' => '🗂️',
    'title' => 'Tabbed Container',
    'tabs' => [
        'overview' => [
            'label' => 'Overview',
        ],
        'activity' => [
            'label' => 'Activity',
        ],
        'settings' => [
            'label' => 'Settings',
        ],
        'advanced' => [
            'label' => 'Advanced',
        ],
    ],
    'content' => [
        'overview_title' => 'General overview',
        'overview_body' => 'The overview tab uses assignment by id and defines the initial state of the container.',
        'activity_log' => 'The Activity tab was associated using its visible label, not the internal id.',
        'settings_copy' => 'Settings is closable. When closed, backend updates the tab definition.',
        'advanced_info' => 'This tab does not define colors, so it uses theme default colors through CSS inheritance.',
    ],
    'toasts' => [
        'tab_closed' => 'Tab closed: :tab',
    ],
];
