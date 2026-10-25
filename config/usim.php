<?php

use App\UI\Screens\Home;

return [

    /*
    |--------------------------------------------------------------------------
    | Front Store Key
    |--------------------------------------------------------------------------
    | Clave de almacenamiento del cliente en el header y payload.
    */
    'front_store_key' => env('FRONT_STORE_KEY', 'usim-posts-store-key'),

    /*
    |--------------------------------------------------------------------------
    | UI Screens Namespace & Path
    |--------------------------------------------------------------------------
    */
    'screens_namespace' => 'App\\UI\\Screens',
    'screens_path' => app_path('UI/Screens'),

    /*
    |--------------------------------------------------------------------------
    | Default Layout & Menu Screen
    |--------------------------------------------------------------------------
    */
    'default_layout' => 'App\\UI\\Layouts\\MainLayout',
    'default_menu_screen' => 'App\\UI\\Screens\\Menu',

    /*
    |--------------------------------------------------------------------------
    | Application Name & API Base URL
    |--------------------------------------------------------------------------
    */
    'app_name' => env('APP_NAME', 'Gestor de Posts USIM'),
    'api_url' => env('API_BASE_URL', env('APP_URL')),

    /*
    |--------------------------------------------------------------------------
    | Upload Disk
    |--------------------------------------------------------------------------
    */
    'upload_disk' => env('UPLOAD_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Models Configuration
    |--------------------------------------------------------------------------
    */
    'models' => [
        'user' => \App\Models\User::class,
        'device' => \App\Models\Device::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Organizational Units (Units)
    |--------------------------------------------------------------------------
    | Definimos una única unidad ('lobby') para toda la aplicación.
    */
    'units' => [
        'structure' => [
            'lobby' => [
                'parent' => null,
                'type' => 'system',
                'default_translations' => [
                    'es' => [
                        'display_name' => 'Lobby',
                        'description' => 'Unidad única del sistema.',
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Users Configuration
    |--------------------------------------------------------------------------
    | Usuarios iniciales asignados a la unidad 'lobby'.
    */
    'users' => [
        'root' => [
            'first_name' => env('ROOT_FIRST_NAME', 'Root'),
            'last_name' => env('ROOT_LAST_NAME', 'User'),
            'email' => env('ROOT_EMAIL', 'root@example.com'),
            'password' => env('ROOT_PASSWORD', '11111111'),
            'unit_roles' => [
                'lobby' => ['root'],
            ],
        ],

        'admin' => [
            'first_name' => 'Admin',
            'last_name' => 'Posts',
            'email' => 'admin@example.com',
            'password' => '11111111',
            'unit_roles' => [
                'lobby' => ['admin'],
            ],
        ],

        'publisher' => [
            'first_name' => 'Autor',
            'last_name' => 'Publicador',
            'email' => 'publisher@example.com',
            'password' => '11111111',
            'unit_roles' => [
                'lobby' => ['publisher'],
            ],
        ],

        'reviewer' => [
            'first_name' => 'Director',
            'last_name' => 'Revisor',
            'email' => 'reviewer@example.com',
            'password' => '11111111',
            'unit_roles' => [
                'lobby' => ['reviewer'],
            ],
        ],

        'registered' => [
            'first_name' => 'Usuario',
            'last_name' => 'Registrado',
            'email' => 'registered@example.com',
            'password' => '11111111',
            'unit_roles' => [
                'lobby' => ['registered'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Devices Configuration
    |--------------------------------------------------------------------------
    */
    'devices' => [],

    /*
    |--------------------------------------------------------------------------
    | Roles Configuration
    |--------------------------------------------------------------------------
    | Roles del sistema:
    | - guest: usuario no autenticado (por defecto cuando no ha iniciado sesión)
    | - registered: usuario recién registrado en espera de asignación de rol
    | - publisher: usuario que puede crear y publicar posts
    | - admin: usuario que puede eliminar posts y asignar roles (publisher/admin)
    */
    'roles' => [
        'root' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Raíz',
                    'description' => 'Acceso total al sistema.',
                ],
            ],
            'priority' => 1,
            'home_screen' => Home::class,
            'permissions' => [
                'home.access',
                'publish_post.access',
                'posts.review',
                'posts.delete',
                'admin.users_manager.access',
                'manage.roles',
                'create.users',
                'remove.users',
            ],
        ],

        'admin' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Administrador',
                    'description' => 'Puede eliminar posts y asignar el rol de publisher o admin a usuarios registrados.',
                ],
            ],
            'priority' => 2,
            'home_screen' => Home::class,
            'permissions' => [
                'home.access',
                'publish_post.access',
                'posts.review',
                'posts.delete',
                'admin.users_manager.access',
                'manage.roles',
                'create.users',
                'remove.users',
            ],
        ],

        'reviewer' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Revisor',
                    'description' => 'Puede revisar propuestas de posts, solicitar correcciones o aprobar su publicación.',
                ],
            ],
            'priority' => 3,
            'home_screen' => Home::class,
            'permissions' => [
                'home.access',
                'posts.review',
            ],
        ],

        'publisher' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Publicador',
                    'description' => 'Puede crear y publicar nuevos posts con imagen.',
                ],
            ],
            'priority' => 4,
            'home_screen' => Home::class,
            'permissions' => [
                'home.access',
                'publish_post.access',
            ],
        ],

        'registered' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Usuario Registrado',
                    'description' => 'Usuario recién registrado pendiente de asignación de rol.',
                ],
            ],
            'priority' => 5,
            'home_screen' => Home::class,
            'permissions' => [
                'home.access',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions Configuration
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'manage.roles' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Gestionar Roles',
                    'description' => 'Permite asignar y editar roles y permisos.',
                ],
            ],
        ],
        'create.users' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Crear Usuarios',
                    'description' => 'Permite crear nuevos usuarios en el sistema.',
                ],
            ],
        ],
        'remove.users' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Eliminar Usuarios',
                    'description' => 'Permite eliminar usuarios del sistema.',
                ],
            ],
        ],
        'publish_post.access' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Publicar Posts',
                    'description' => 'Permite acceder a la pantalla de creación de posts.',
                ],
            ],
        ],
        'posts.delete' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Eliminar Posts',
                    'description' => 'Permite eliminar cualquier post publicado.',
                ],
            ],
        ],
        'posts.review' => [
            'default_translations' => [
                'es' => [
                    'display_name' => 'Revisar Posts',
                    'description' => 'Permite moderar, solicitar cambios y aprobar propuestas de publicación.',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Registering Role
    |--------------------------------------------------------------------------
    */
    'default_registering_role' => 'registered',

    /*
    |--------------------------------------------------------------------------
    | Internationalization (i18n) - Solo Español
    |--------------------------------------------------------------------------
    */
    'i18n' => [
        'default_locale' => env('USIM_DEFAULT_LOCALE', env('APP_LOCALE', 'es')),
        'fallback_locale' => env('USIM_FALLBACK_LOCALE', 'es'),
        'auto_key_max_length' => (int) env('USIM_I18N_AUTO_KEY_MAX_LENGTH', 30),
        'log_channel' => env('USIM_I18N_LOG_CHANNEL', 'i18n'),
        'log_autokey_suggestions' => env('USIM_I18N_LOG_AUTOKEY_SUGGESTIONS', false),
        'languages' => [
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'active' => true],
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'active' => false],
        ],
        'i18n_key_prefixes' => [
            'role' => 'role.',
            'permission' => 'permission.',
            'screen' => 'screens.',
            'screens' => 'screens.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Headless Mode
    |--------------------------------------------------------------------------
    */
    'headless_mode' => env('USIM_HEADLESS_MODE', false),
];
