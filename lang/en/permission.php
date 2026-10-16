<?php

return [
    'debug' => [
        'logs' => [
            'name' => 'View System Logs',
            'description' => 'Allows viewing system activity and error logs.',
        ],
        'access' => [
            'name' => 'Access Debug Tools',
            'description' => 'Allows access to debugging and diagnostic tools.',
        ],
    ],
    'manage' => [
        'users' => [
            'name' => 'Manage Users',
            'description' => 'Allows creating, editing, and deleting users.',
        ],
        'roles' => [
            'name' => 'Manage Roles',
            'description' => 'Allows creating, editing, and deleting roles and their permissions.',
        ],
    ],
    'auth' => [
        'profile' => [
            'access' => [
                'name' => 'Access Auth Profile',
                'description' => 'Permission to access Auth Profile.',
            ],
        ],
        'edit_user' => [
            'access' => [
                'name' => 'Access Auth Edit User',
                'description' => 'Permission to access Auth Edit User.',
            ],
        ],
    ],
    'admin' => [
        'translate_manager' => [
            'access' => [
                'name' => 'Access Admin Translate Manager',
                'description' => 'Permission to access Admin Translate Manager.',
            ],
        ],
        'users_manager' => [
            'access' => [
                'name' => 'Access Admin Users Manager',
                'description' => 'Permission to access Admin Users Manager.',
            ],
        ],
        'admin_device_pairing' => [
            'access' => [
                'name' => 'Access Admin Admin Device Pairing',
                'description' => 'Permission to access Admin Admin Device Pairing.',
            ],
        ],
        'edit_user' => [
            'access' => [
                'name' => 'Access Admin Edit User',
                'description' => 'Permission to access Admin Edit User.',
            ],
        ],
        'edit_translation_dialog' => [
            'access' => [
                'name' => 'Access Admin Edit Translation Dialog',
                'description' => 'Permission to access Admin Edit Translation Dialog.',
            ],
        ],
    ],
    'home' => [
        'access' => [
            'name' => 'Access Home',
            'description' => 'Permission to access Home.',
        ],
    ],
    'write' => [
        'name' => 'Write Access',
        'description' => 'Allows creating and modifying content.',
    ],
    'approve' => [
        'publications' => [
            'name' => 'Approve Publications',
            'description' => 'Allows approving or rejecting publications submitted by users.',
        ],
    ],
    'registered' => [
        'access' => [
            'name' => 'Access Registered',
            'description' => 'Permission to access Registered.',
        ],
    ],
    'device' => [
        'kiosk' => [
            'access' => [
                'name' => 'Access Device Kiosk',
                'description' => 'Permission to access Device Kiosk.',
            ],
        ],
        'edit_device' => [
            'access' => [
                'name' => 'Access Device Edit Device',
                'description' => 'Permission to access Device Edit Device.',
            ],
        ],
        'link' => [
            'access' => [
                'name' => 'Access Device Link',
                'description' => 'Permission to access Device Link.',
            ],
        ],
    ],
    'create' => [
        'users' => [
            'name' => 'Create Users',
            'description' => 'Allows creating new users in the system.',
        ],
    ],
    'remove' => [
        'users' => [
            'name' => 'Remove Users',
            'description' => 'Allows removing users from the system.',
        ],
    ],
    'pepito' => [
        'access' => [
            'name' => 'Access Pepito',
            'description' => 'Permission to access Pepito.',
        ],
    ],
    'home_stub' => [
        'access' => [
            'name' => 'Access Home Stub',
            'description' => 'Permission to access Home Stub.',
        ],
    ],
];
