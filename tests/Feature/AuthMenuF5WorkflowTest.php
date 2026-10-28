<?php

// @usim: feature="core", type="test"

use App\Models\User;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Home;
use App\UI\Screens\Menu;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Tests\TestCase;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
});

it('verifies menu state persists across F5 reloads on login and resets on logout', function () {
    /** @var TestCase $this */

    // 1. Visita inicial como invitado
    $guestResponse = $this->getJson('/api/ui/home');
    $guestResponse->assertOk();
    $guestUserMenu = findComponentByName($guestResponse->json(), 'user_menu');
    expect($guestUserMenu['trigger']['label'] ?? null)->toBe('⚙️');

    // 2. Login SPA
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => bcrypt('password123'),
    ]);

    $loginUiResponse = getScreenJson($this, Login::class);
    $loginRootId = serviceRootComponentId($loginUiResponse->json(), Login::class);

    $loginResponse = $this->postJson('/api/ui-event', [
        'component_id' => $loginRootId,
        'event' => 'submit',
        'action' => 'submit_login',
        'parameters' => [
            'email' => 'john@example.com',
            'password' => 'password123',
            'remember' => false,
        ],
    ]);
    $loginResponse->assertOk();

    // 3. Prueba de F5 tras Login
    $f5Response = $this->getJson('/api/ui/home');
    $f5Response->assertOk();
    $f5UserMenu = findComponentByName($f5Response->json(), 'user_menu');
    expect($f5UserMenu['trigger']['label'] ?? null)->toContain('John Doe');

    // 4. Logout SPA
    $menuRootId = serviceRootComponentId($f5Response->json(), Menu::class);
    $logoutResponse = $this->postJson('/api/ui-event', [
        'component_id' => $menuRootId,
        'event' => 'action',
        'action' => 'confirm_logout',
        'parameters' => [],
    ]);
    $logoutResponse->assertOk();

    // 5. Prueba de F5 tras Logout
    $f5GuestResponse = $this->getJson('/api/ui/home');
    $f5GuestResponse->assertOk();
    $f5GuestUserMenu = findComponentByName($f5GuestResponse->json(), 'user_menu');
    expect($f5GuestUserMenu['trigger']['label'] ?? null)->toBe('⚙️');
});

