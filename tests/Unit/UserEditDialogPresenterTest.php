<?php

use App\UI\Screens\Admin\Presenters\UserEditDialogPresenter;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Support\Facades\Lang;

it('returns null when required identity fields are missing or invalid', function (array $invalidUser) {
    $presenter = new UserEditDialogPresenter;

    expect($presenter->present($invalidUser))->toBeNull();
})->with([
    'missing id' => [['name' => 'Jane', 'email' => 'jane@example.com']],
    'invalid id type' => [['id' => ['bad'], 'name' => 'Jane', 'email' => 'jane@example.com']],
    'missing name' => [['id' => 1, 'email' => 'jane@example.com']],
    'invalid name type' => [['id' => 1, 'name' => 123, 'email' => 'jane@example.com']],
    'missing email' => [['id' => 'uuid-1', 'name' => 'Jane']],
    'invalid email type' => [['id' => 'uuid-1', 'name' => 'Jane', 'email' => null]],
]);

it('presents minimal valid user with integer or string id', function () {
    $presenter = new UserEditDialogPresenter;

    $result = $presenter->present([
        'id' => 'user-42',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    expect($result)->toBe([
        'id' => 'user-42',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
});

it('normalizes optional flags, roles, operational units, and units with roles', function () {
    $presenter = new UserEditDialogPresenter;

    $result = $presenter->present([
        'id' => 7,
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'email_verified_at' => null,
        'is_in_lobby' => false,
        'has_operational_units' => true,
        'roles' => [
            'admin',
            ['name' => 'editor', 'id' => 2],
            ['name' => 999],
            123,
        ],
        'operational_units' => [
            ['id' => 1, 'slug' => 'ops', 'name' => 'Operations'],
            ['id' => 'u-2', 'slug' => 'lab', 'name' => 'Laboratory'],
            'not-an-array',
            ['id' => null, 'slug' => 'invalid', 'name' => 'Invalid'],
        ],
        'units_with_roles' => [
            'ops' => ['admin', 123, 'operator'],
            0 => ['ignored'],
            'broken' => 'not-an-array',
        ],
    ]);

    expect($result)->toBe([
        'id' => 7,
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'email_verified_at' => null,
        'is_in_lobby' => false,
        'has_operational_units' => true,
        'roles' => [
            'admin',
            ['name' => 'editor'],
        ],
        'operational_units' => [
            ['id' => 1, 'slug' => 'ops', 'name' => 'Operations'],
            ['id' => 'u-2', 'slug' => 'lab', 'name' => 'Laboratory'],
        ],
        'units_with_roles' => [
            'ops' => ['admin', 'operator'],
        ],
    ]);
});

it('formats active unit using translated display name or capitalized slug fallback', function () {
    $presenter = new UserEditDialogPresenter;

    $untranslatedUnit = new UsimUnit(['slug' => 'custom_unit']);
    $untranslatedUnit->id = 10;

    $resultFallback = $presenter->present([
        'id' => 1,
        'name' => 'Test User',
        'email' => 'test@example.com',
    ], $untranslatedUnit);

    expect($resultFallback['active_unit'])->toBe([
        'id' => 10,
        'slug' => 'custom_unit',
        'name' => 'Custom_unit',
    ]);

    Lang::addLines(['unit. translated_unit.display_name' => 'Translated Unit'], app()->getLocale());
    $translatedUnit = new class(['slug' => 'ops']) extends UsimUnit
    {
        public function getDisplayNameAttribute(): string
        {
            return 'Unidad Operativa';
        }
    };
    $translatedUnit->id = 20;

    $resultTranslated = $presenter->present([
        'id' => 1,
        'name' => 'Test User',
        'email' => 'test@example.com',
    ], $translatedUnit);

    expect($resultTranslated['active_unit'])->toBe([
        'id' => 20,
        'slug' => 'ops',
        'name' => 'Unidad Operativa',
    ]);
});
