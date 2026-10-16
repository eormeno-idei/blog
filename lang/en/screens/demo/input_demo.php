<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Inputs',
    'icon' => '⌨️',
    'actions' => [
        'validate' => 'Validate',
    ],
    'instruction' => 'Enter a name with at least 3 characters and click "Validate"',
    'name' => [
        'label' => 'Your Name',
        'placeholder' => 'Enter your name here...',
    ],
    'result' => [
        'fix_error' => 'Please fix the error above',
        'initial' => 'Result will appear here',
        'valid_name' => 'Valid name: ":name"',
    ],
    'title' => 'Input Component Demo',
    'validation' => [
        'name_min' => 'Name must be at least 3 characters',
        'name_required' => 'Name is required',
    ],
];
