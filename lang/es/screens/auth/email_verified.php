<?php

// @usim: feature="core", type="lang"

return [
    'menu_title' => 'Email Verified',
    'icon' => '',
    'actions' => [
        'go_to_home' => 'Volver al Inicio',
        'go_to_login' => 'Ir al Login',
        'resend' => 'Solicitar Nuevo Enlace',
    ],
    'already_verified' => [
        'card_description' => 'Su cuenta ya está activa. Puede iniciar sesión normalmente.',
        'card_title' => 'Email Verificado',
        'message' => 'Su email ya ha sido verificado anteriormente',
    ],
    'error' => [
        'card_description' => 'El enlace de verificación puede haber expirado o ser inválido. Por favor, solicite un nuevo enlace de verificación.',
        'card_title' => 'Verificación Fallida',
        'message' => 'Error al verificar el email',
    ],
    'errors' => [
        'expired' => 'El enlace de verificación ha expirado. Solicite uno nuevo.',
        'generic' => 'No se pudo verificar el email. El enlace puede haber expirado o ser inválido.',
        'invalid_params' => 'Enlace de verificación inválido. Faltan parámetros requeridos.',
    ],
    'failed_title' => 'Verificación Fallida',
    'go_to_login' => 'Ir al Login',
    'loading' => 'Verificando su email...',
    'request_new_link' => 'Por favor, inicie sesión para solicitar un nuevo enlace de verificación',
    'title' => 'Verificación Completa',
    'toast' => [
        'redirect_to_login' => 'Por favor, inicie sesión para solicitar un nuevo enlace de verificación',
    ],
    'verified' => [
        'card_description' => 'Su cuenta ha sido activada correctamente. Ya puede iniciar sesión y disfrutar de todos los servicios disponibles.',
        'card_title' => 'Verificación Completa',
        'message' => 'Su email ha sido verificado satisfactoriamente',
        'subtitle' => 'Ahora puede acceder a todas las funcionalidades de la plataforma',
    ],
    'verified_title' => 'Email Verificado',
];
