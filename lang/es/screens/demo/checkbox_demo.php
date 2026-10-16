<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Checkboxs',
    'icon' => '✅',
    'actions' => [
        'submit' => 'Enviar Selección',
    ],
    'instruction' => 'Selecciona tus lenguajes de programación preferidos:',
    'options' => [
        'javascript' => 'JavaScript',
        'python' => 'Python',
    ],
    'result' => [
        'initial' => 'Haz tu selección arriba',
        'javascript_deselected' => 'JavaScript deseleccionado',
        'javascript_selected' => 'JavaScript seleccionado!',
        'python_deselected' => 'Python deseleccionado',
        'python_selected' => 'Python seleccionado!',
        'submitted' => 'Enviado! Tus selecciones: :languages',
        'submitted_toast' => 'Enviado! Tus selecciones: :languages',
    ],
    'title' => 'Demo de Componente Checkbox',
    'validation' => [
        'minimum_one' => 'Error: debes seleccionar al menos un lenguaje',
        'python_requires_javascript' => 'Debes seleccionar JavaScript antes de seleccionar Python!',
    ],
];
