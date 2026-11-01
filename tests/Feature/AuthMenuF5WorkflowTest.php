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

it('replaces screen and updates menu when logging in from modal after a delay', function () {
    /** @var TestCase $this */

    // 1. Inicio de la app: se despliega Home con el usuario guest
    $guestResponse = $this->getJson('/api/ui/home');
    $guestResponse->assertOk();
    $guestUserMenu = findComponentByName($guestResponse->json(), 'user_menu');
    expect($guestUserMenu['trigger']['label'] ?? null)->toBe('⚙️');

    // 2. Simulación de demora de unos minutos entre paso 1 y paso 2 (expira caché corta de 60s)
    $clientRepo = app(\Idei\Usim\Contracts\UIStateRepositoryInterface::class);
    $clientId = $clientRepo->getOrCreateClientId();
    \Illuminate\Support\Facades\Cache::forget("ui_open_screens:{$clientId}");
    \Illuminate\Support\Facades\Cache::forget("ui_open_classes:{$clientId}");

    // 3. Selecciona "Login", se abre el modal
    $homeLoginBtn = findComponentByName($guestResponse->json(), 'btn_go_login');
    expect($homeLoginBtn)->not->toBeNull();
    $openLoginResponse = $this->postJson('/api/ui-event', [
        'component_id' => (int) $homeLoginBtn['_json_key'],
        'event' => 'action',
        'action' => 'open_login',
        'parameters' => [],
    ]);
    $openLoginResponse->assertOk();

    // 4. Usuario envía credenciales válidas en el modal
    $user = User::factory()->create([
        'name' => 'Alice Admin',
        'email' => 'alice@example.com',
        'password' => bcrypt('password123'),
    ]);

    $loginUiResponse = getScreenJson($this, Login::class);
    $loginRootId = serviceRootComponentId($loginUiResponse->json(), Login::class);

    $loginResponse = $this->postJson('/api/ui-event', [
        'component_id' => $loginRootId,
        'event' => 'submit',
        'action' => 'submit_login',
        'parameters' => [
            'email' => 'alice@example.com',
            'password' => 'password123',
            'remember' => false,
        ],
    ]);
    $loginResponse->assertOk();
    $json = $loginResponse->json();

    // Debe cerrar el modal
    expect($json['action'] ?? null)->toBe('close_modal');

    // Debe emitir instrucción de navegación para reemplazar la pantalla Home
    expect($json['navigate'] ?? null)->not->toBeNull()
        ->and($json['navigate']['url'] ?? null)->toBe('/home');

    // Debe actualizar el menú con el nombre del usuario logueado en la misma respuesta SPA
    $menuUpdate = collect($json)->first(fn ($val) => is_array($val) && isset($val['trigger']['label']));
    expect($menuUpdate)->not->toBeNull()
        ->and($menuUpdate['trigger']['label'] ?? null)->toContain('Alice Admin');
});


