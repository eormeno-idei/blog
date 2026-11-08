<?php

return [
    'debug' => [
        'logs' => [
            'name' => 'Ver Registros del Sistema',
            'description' => 'Permite ver los registros de actividad y errores del sistema.',
        ],
        'access' => [
            'name' => 'Acceder a Herramientas de Depuración',
            'description' => 'Permite acceder a herramientas de depuración y diagnóstico.',
        ],
    ],
    'manage' => [
        'users' => [
            'name' => 'Gestionar Usuarios',
            'description' => 'Permite crear, editar y eliminar usuarios.',
        ],
        'roles' => [
            'name' => 'Gestionar Roles',
            'description' => 'Permite asignar y editar roles y permisos.',
        ],
    ],
    'auth' => [
        'profile' => [
            'access' => [
                'name' => 'Acceder a Auth Profile',
                'description' => 'Permite acceder a la pantalla Auth Profile.',
            ],
        ],
        'edit_user' => [
            'access' => [
                'name' => 'Acceder a Auth Edit User',
                'description' => 'Permite acceder a la pantalla Auth Edit User.',
            ],
        ],
    ],
    'admin' => [
        'translate_manager' => [
            'access' => [
                'name' => 'Acceder a Admin Translate Manager',
                'description' => 'Permite acceder a la pantalla Admin Translate Manager.',
            ],
        ],
        'users_manager' => [
            'access' => [
                'name' => 'Acceder a Admin Users Manager',
                'description' => 'Permite acceder a la pantalla Admin Users Manager.',
            ],
        ],
        'admin_device_pairing' => [
            'access' => [
                'name' => 'Acceder a Admin Admin Device Pairing',
                'description' => 'Permite acceder a la pantalla Admin Admin Device Pairing.',
            ],
        ],
        'edit_user' => [
            'access' => [
                'name' => 'Acceder a Admin Edit User',
                'description' => 'Permite acceder a la pantalla Admin Edit User.',
            ],
        ],
        'edit_translation_dialog' => [
            'access' => [
                'name' => 'Acceder a Admin Edit Translation Dialog',
                'description' => 'Permite acceder a la pantalla Admin Edit Translation Dialog.',
            ],
        ],
    ],
    'home' => [
        'access' => [
            'name' => 'Acceder a Home',
            'description' => 'Permite acceder a la pantalla Home.',
        ],
    ],
    'write' => [
        'name' => 'Acceso de Escritura',
        'description' => 'Permite crear y modificar contenido.',
    ],
    'approve' => [
        'publications' => [
            'name' => 'Aprobar Publicaciones',
            'description' => 'Permite aprobar o rechazar publicaciones enviadas por los usuarios.',
        ],
    ],
    'device' => [
        'kiosk' => [
            'access' => [
                'name' => 'Acceder a Device Kiosk',
                'description' => 'Permite acceder a la pantalla Device Kiosk.',
            ],
        ],
        'edit_device' => [
            'access' => [
                'name' => 'Acceder a Device Edit Device',
                'description' => 'Permite acceder a la pantalla Device Edit Device.',
            ],
        ],
        'link' => [
            'access' => [
                'name' => 'Acceder a Device Link',
                'description' => 'Permite acceder a la pantalla Device Link.',
            ],
        ],
    ],
    'create' => [
        'users' => [
            'name' => 'Crear Usuarios',
            'description' => 'Permite crear nuevos usuarios en el sistema.',
        ],
    ],
    'remove' => [
        'users' => [
            'name' => 'Eliminar Usuarios',
            'description' => 'Permite eliminar usuarios del sistema.',
        ],
    ],
    'home_stub' => [
        'access' => [
            'name' => 'Acceder a Home Stub',
            'description' => 'Permite acceder a la pantalla Home Stub.',
        ],
    ],
    'publish_post' => [
        'access' => [
            'name' => 'Publicar Posts',
            'description' => 'Permite acceder a la pantalla de creación de posts.',
        ],
    ],
    'posts' => [
        'delete' => [
            'name' => 'Eliminar Posts',
            'description' => 'Permite eliminar posts.',
        ],
        'review' => [
            'name' => 'Revisar Posts',
            'description' => 'Permite moderar, solicitar cambios y aprobar propuestas de publicación.',
        ],
        'publish' => [
            'name' => 'Publicar Posts',
            'description' => 'Permite publicar posts.',
        ],
    ],
    'post' => [
        'review_post' => [
            'access' => [
                'name' => 'Acceder a Post Review Post',
                'description' => 'Permite acceder a la pantalla Post Review Post.',
            ],
        ],
        'view' => [
            'any' => [
                'name' => 'Ver Todos los Posts',
                'description' => 'Permite ver todos los posts del sistema.',
            ],
            'own' => [
                'name' => 'Ver Propios Posts',
                'description' => 'Permite ver los posts creados por el usuario.',
            ],
        ],
        'delete' => [
            'name' => 'Eliminar Posts',
            'description' => 'Permite eliminar post.',
        ],
        'review' => [
            'name' => 'Revisar Posts',
            'description' => 'Permite moderar, solicitar cambios y aprobar propuestas de publicación.',
        ],
        'parts' => [
            'initial_data' => [
                'access' => [
                    'name' => 'Acceder a Post Parts Initial Data',
                    'description' => 'Permite acceder a la pantalla Post Parts Initial Data.',
                ],
            ],
            'reel_studio' => [
                'access' => [
                    'name' => 'Acceder a Post Parts Reel Studio',
                    'description' => 'Permite acceder a la pantalla Post Parts Reel Studio.',
                ],
            ],
            'sumary_publish' => [
                'access' => [
                    'name' => 'Acceder a Post Parts Sumary Publish',
                    'description' => 'Permite acceder a la pantalla Post Parts Sumary Publish.',
                ],
            ],
            'reviewer_chat' => [
                'access' => [
                    'name' => 'Acceder a Post Parts Reviewer Chat',
                    'description' => 'Permite acceder a la pantalla Post Parts Reviewer Chat.',
                ],
            ],
            'date_time_data' => [
                'access' => [
                    'name' => 'Acceder a Post Parts Date Time Data',
                    'description' => 'Permite acceder a la pantalla Post Parts Date Time Data.',
                ],
            ],
            'video_studio' => [
                'access' => [
                    'name' => 'Acceder a Post Parts Video Studio',
                    'description' => 'Permite acceder a la pantalla Post Parts Video Studio.',
                ],
            ],
            'a_i_reel_studio' => [
                'access' => [
                    'name' => 'Acceder a Post Parts A I Reel Studio',
                    'description' => 'Permite acceder a la pantalla Post Parts A I Reel Studio.',
                ],
            ],
            'old_reel_studio' => [
                'access' => [
                    'name' => 'Acceder a Post Parts Old Reel Studio',
                    'description' => 'Permite acceder a la pantalla Post Parts Old Reel Studio.',
                ],
            ],
            'reel_scene_viewer' => [
                'access' => [
                    'name' => 'Acceder a Post Parts Reel Scene Viewer',
                    'description' => 'Permite acceder a la pantalla Post Parts Reel Scene Viewer.',
                ],
            ],
        ],
    ],
    'registered_home' => [
        'access' => [
            'name' => 'Acceder a Registered Home',
            'description' => 'Permite acceder a la pantalla Registered Home.',
        ],
    ],
];
