<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Formularios',
    'icon' => '📝',
    'actions' => [
        'submit' => 'Enviar Formulario',
    ],
    'email' => [
        'label' => 'Correo',
        'placeholder' => 'Ingresa tu correo',
    ],
    'instruction' => 'Completa el formulario (todos los campos son obligatorios):',
    'name' => [
        'label' => 'Nombre',
        'placeholder' => 'Ingresa tu nombre',
    ],
    'result' => [
        'errors' => 'Corrige los errores anteriores',
        'initial' => 'Completa el formulario para continuar',
        'success' => 'Formulario enviado correctamente!

Nombre: :name
Correo: :email',
    ],
    'title' => 'Demo de Componente Formulario',
    'toast' => [
        'correct_errors' => 'Corrige los errores del formulario',
    ],
    'validation' => [
        'email_invalid' => 'El correo no es válido',
        'email_required' => 'El correo es obligatorio',
        'name_min' => 'El nombre debe tener al menos 2 caracteres',
        'name_required' => 'El nombre es obligatorio',
    ],
];
