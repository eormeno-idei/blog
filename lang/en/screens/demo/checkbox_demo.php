<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Checkboxs',
    'icon' => '✅',
    'actions' => [
        'submit' => 'Submit Selection',
    ],
    'instruction' => 'Select your preferred programming languages:',
    'options' => [
        'javascript' => 'JavaScript',
        'python' => 'Python',
    ],
    'result' => [
        'initial' => 'Make your selection above',
        'javascript_deselected' => 'JavaScript deselected',
        'javascript_selected' => 'JavaScript selected!',
        'python_deselected' => 'Python deselected',
        'python_selected' => 'Python selected!',
        'submitted' => 'Submitted! Your selections: :languages',
        'submitted_toast' => 'Submitted! Your selections: :languages',
    ],
    'title' => 'Checkbox Component Demo',
    'validation' => [
        'minimum_one' => 'Error: You must select at least one language',
        'python_requires_javascript' => 'You must select JavaScript first before selecting Python!',
    ],
];
