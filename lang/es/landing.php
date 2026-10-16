<?php

// @usim: feature="core", type="lang"

return [
    'badge' => 'USIM framework listo',
    'lead' => 'Construye interfaces de produccion desde pantallas PHP con una arquitectura server-driven. USIM mantiene el estado, la validacion, la autorizacion y el flujo de interaccion en backend, mientras el cliente renderiza el payload inicial y las actualizaciones incrementales.',
    'actions' => [
        'open_app' => 'Abrir aplicacion',
        'documentation' => 'Documentacion',
    ],
    'highlights' => [
        'screen_backend_title' => 'UI backend-driven por Screens',
        'screen_backend_text' => 'Define paginas completas en PHP con ids de componentes deterministas.',
        'incremental_title' => 'Actualizaciones incrementales',
        'incremental_text' => 'Envia solo diffs de UI despues de eventos para interacciones rapidas y predecibles.',
        'auth_i18n_title' => 'Listo para auth e i18n',
        'auth_i18n_text' => 'Incluye patrones para login, perfiles, permisos y cambio de idioma.',
    ],
    'quickstart' => [
        'title' => 'Inicio rapido',
        'steps' => [
            'first' => 'Define tu Screen en PHP bajo app/UI/Screens.',
            'second' => 'Dispara acciones con handlers on<ActionName>.',
            'third' => 'Mantene la fuente de verdad en el estado del backend.',
        ],
    ],
];
