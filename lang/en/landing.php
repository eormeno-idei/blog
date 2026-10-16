<?php

// @usim: feature="core", type="lang"

return [
    'badge' => 'USIM framework ready',
    'lead' => 'Build production interfaces from PHP screens with a server-driven architecture. USIM keeps state, validation, authorization and interaction flow in backend code, while the client renders initial payloads and incremental updates.',
    'actions' => [
        'open_app' => 'Open app',
        'documentation' => 'Documentation',
    ],
    'highlights' => [
        'screen_backend_title' => 'Screen-based backend UI',
        'screen_backend_text' => 'Define complete pages in PHP with deterministic component ids.',
        'incremental_title' => 'Incremental updates',
        'incremental_text' => 'Send only UI diffs after events to keep interactions fast and predictable.',
        'auth_i18n_title' => 'Ready for auth and i18n',
        'auth_i18n_text' => 'Includes patterns for login, profiles, permissions and language switching.',
    ],
    'quickstart' => [
        'title' => 'Quick Start',
        'steps' => [
            'first' => 'Define your screen in PHP under app/UI/Screens.',
            'second' => 'Trigger actions with on<ActionName> handlers.',
            'third' => 'Keep source of truth in backend state.',
        ],
    ],
];
