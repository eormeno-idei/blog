<?php

return [
    'menu_title' => 'Registered Home',
    'icon' => '',
    'greeting_title' => '¡Hola, :name!',
    'registered_at' => 'Registrado :time',
    'verified' => 'Verificado',
    'unverified' => 'Pendiente de verificación',
    'attributes' => [
        'email' => 'Correo',
        'verification' => 'Verificación',
        'status' => 'Estado',
        'status_waiting' => 'Sala de espera',
    ],
    'actions' => [
        'complete_profile' => 'Completar mi perfil',
        'explore_posts' => 'Ver publicaciones',
    ],
    'welcome' => [
        'eyebrow' => 'Sala de espera',
        'subtitle' => 'Tu cuenta fue registrada correctamente y está pendiente de asignación de rol.',
        'description' => 'Un administrador revisará tu registro y te asignará los permisos correspondientes. En breve recibirás un correo con la confirmación.',
        'badge' => 'Pendiente de aprobación',
        'footer_text' => 'Mientras tanto, puedes completar tu perfil o explorar las publicaciones.',
    ],
    'step_profile' => [
        'eyebrow' => 'Paso 1',
        'title' => 'Completa tu perfil',
        'description' => 'Actualiza tu nombre, foto de perfil y revisa tus datos de contacto mientras esperas.',
        'badge' => 'Disponible',
    ],
    'step_approval' => [
        'eyebrow' => 'Paso 2',
        'title' => 'Confirmación de rol',
        'description' => 'Cuando un administrador apruebe tu acceso, recibirás una notificación y podrás usar tus nuevas funciones.',
        'badge' => 'En espera',
    ],
];
