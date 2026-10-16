<?php

return [
    'root' => [
        'description' => 'User with total and unconditional access to all features.',
        'name' => 'Root',
    ],
    'admin' => [
        'description' => 'Manages the system and users.',
        'name' => 'Administrator',
    ],
    'registered' => [
        'description' => 'User with basic access to the system.',
        'name' => 'Registered User',
        'waiting' => 'Waiting (Registered)',
    ],
    'approved' => [
        'description' => 'Approved user with access to additional features.',
        'name' => 'Approved',
    ],
    'translator' => [
        'description' => 'User responsible for managing translations.',
        'name' => 'Translator',
    ],
    'developer' => [
        'description' => 'User with access to development and debugging tools.',
        'name' => 'Developer',
    ],
    'user' => [
        'description' => 'User with basic access to the system.',
        'name' => 'User',
    ],
    'guest' => [
        'name' => 'Guest',
        'description' => 'User with basic access to the system.',
    ],
    'member' => [
        'name' => 'Member',
        'description' => 'User with access to specific features.',
    ],
    'none' => 'None',
    'smart_tv' => [
        'name' => 'Smart TV',
        'description' => 'Kiosk mode display.',
    ],
    'sensor' => [
        'name' => 'Hardware Sensor',
        'description' => 'IoT data provider.',
    ],
];
