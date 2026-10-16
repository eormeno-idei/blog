<?php

// @usim: feature="core", type="lang"

return [
    'icon' => '↔️',
    'menu_title' => 'Splits',

    'title' => 'Split Container Demo',
    'intro' => 'Configure orientation, initial size and collapse behavior. The split bar is draggable.',
    'orientation' => [
        'label' => 'Orientation',
        'options' => [
            'horizontal' => 'Horizontal',
            'vertical' => 'Vertical',
        ],
    ],
    'split_size' => [
        'label' => 'First pane width/height (e.g. 35% or 360px)',
        'placeholder' => '45%',
    ],
    'splitter_size' => [
        'label' => 'Split bar size (e.g. 8px)',
        'placeholder' => '8px',
    ],
    'draggable' => 'Draggable',
    'collapsible' => 'Collapsible',
    'collapse_target' => [
        'label' => 'Collapsible pane',
        'options' => [
            'first' => 'First pane',
            'second' => 'Second pane',
        ],
    ],
    'actions' => [
        'apply_sizes' => 'Apply sizes',
        'toggle_collapse' => 'Toggle collapse',
        'reset_demo' => 'Reset demo',
        'collapse_first' => 'Collapse pane A',
        'collapse_second' => 'Collapse pane B',
    ],
    'panes' => [
        'first' => [
            'title' => 'Pane A',
            'description' => 'This pane represents primary content.',
        ],
        'second' => [
            'title' => 'Pane B',
            'description' => 'Use the middle bar to resize live.',
        ],
    ],
];
