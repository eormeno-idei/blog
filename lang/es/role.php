<?php

return [
    'root' => [
        'description' => 'Acceso total al sistema.',
        'name' => 'Raíz',
    ],
    'admin' => [
        'description' => 'Puede eliminar posts y asignar el rol de publisher o admin a usuarios registrados.',
        'name' => 'Administrador',
    ],
    'registered' => [
        'description' => 'Usuario recién registrado pendiente de asignación de rol.',
        'name' => 'Usuario Registrado',
        'waiting' => 'En espera (Registrado)',
    ],
    'approved' => [
        'description' => 'Usuario aprobado con acceso a funciones adicionales.',
        'name' => 'Aprobado',
    ],
    'translator' => [
        'description' => 'Usuario responsable de gestionar las traducciones.',
        'name' => 'Traductor',
    ],
    'developer' => [
        'description' => 'Usuario con acceso a herramientas de desarrollo y depuración.',
        'name' => 'Programador',
    ],
    'user' => [
        'description' => 'Usuario con acceso básico al sistema.',
        'name' => 'Usuario',
    ],
    'guest' => [
        'name' => 'Invitado',
        'description' => 'Usuario con acceso básico al sistema.',
    ],
    'member' => [
        'name' => 'Miembro',
        'description' => 'Usuario con acceso a funciones específicas.',
    ],
    'none' => 'Ninguno',
    'smart_tv' => [
        'name' => 'Smart TV',
        'description' => 'Pantalla en modo kiosco.',
    ],
    'sensor' => [
        'name' => 'Sensor de Hardware',
        'description' => 'Proveedor de datos IoT.',
    ],
    'publisher' => [
        'name' => 'Publicador',
        'description' => 'Puede crear y publicar nuevos posts con imagen.',
    ],
    'reviewer' => [
        'name' => 'Revisor',
        'description' => 'Puede revisar propuestas de posts, solicitar correcciones o aprobar su publicación.',
    ],
];
