<?php
// @usim: feature="admin", type="test"
use App\Models\User;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Home;

beforeEach(function () {
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
});

it('loads login screen with expected components and actions', function () {
    /** @var \Tests\TestCase $this */
    $ui = uiScenario($this, Login::class, ['reset' => true]);

    $emailInput = $ui->component('login_email');
    $passwordInput = $ui->component('login_password');
    $submitButton = $ui->component('btn_submit_login');
    $forgotButton = $ui->component('btn_forgot_password');

    $emailInput->expect('type')->toBe('input');
    $emailInput->expect('input_type')->toBe('email');

    $passwordInput->expect('type')->toBe('input');
    $passwordInput->expect('input_type')->toBe('password');

    $submitButton->expect('action')->toBe('submit_login');
    $forgotButton->expect('action')->toBe('navigate_forgot_password');

    $ui->assertNoIssues();
});

it('authenticates configured root user and returns redirect contract', function () {
    /** @var \Tests\TestCase $this */
    $result = $this->loginAs('root');
    /** @var User $adminUser */
    $adminUser = $result['user'];
    $response = $result['response'];

    /** @var class-string<\Idei\Usim\Screen> $expectedScreen */
    $expectedScreen = config('usim.roles.root.home_screen', Home::class);

    $response->assertOk();
    expect($response->json('navigate.url') ?? $response->json('redirect'))->toBe($expectedScreen::getRoutePath());
    expect($response->json('toast.type'))->toBe('success');
    $this->assertAuthenticatedAs($adminUser);
});

it('authenticates configured default registering user role and returns redirect contract', function () {
    $defaultRegisteringRole = config('usim.default_registering_role', 'user');
    /** @var \Tests\TestCase $this */
    $result = $this->loginAs($defaultRegisteringRole);
    /** @var User $regularUser */
    $regularUser = $result['user'];
    $response = $result['response'];

    $response->assertOk();
    expect($response->json('navigate.url') ?? $response->json('redirect'))->not->toBeNull();
    expect($response->json('toast.type'))->toBe('success');
    $this->assertAuthenticatedAs($regularUser);
});

it('authenticates configured test user with multiple units and returns redirect contract', function () {
    /** @var \Tests\TestCase $this */
    $testConfig = config('usim.users.test') ?? [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => '11111111',
    ];
    $unitMain = \Idei\Usim\Models\UsimUnit::firstOrCreate(['slug' => 'main']);
    $unitIdei = \Idei\Usim\Models\UsimUnit::firstOrCreate(['slug' => 'idei']);

    $user = User::factory()->create([
        'name' => $testConfig['first_name'] . ' ' . $testConfig['last_name'],
        'email' => $testConfig['email'],
        'password' => bcrypt($testConfig['password']),
    ]);
    $user->usimUnits()->sync([$unitMain->id, $unitIdei->id]);

    $roleAdmin = \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    $roleTranslator = \Spatie\Permission\Models\Role::findOrCreate('translator', 'web');

    setPermissionsTeamId($unitMain->id);
    $user->assignRole($roleAdmin);

    setPermissionsTeamId($unitIdei->id);
    $user->assignRole($roleTranslator);

    $uiResponse = getScreenJson($this, Login::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => $testConfig['email'],
            'login_password' => $testConfig['password'],
        ],
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');
    expect($response->json('navigate.url') ?? $response->json('redirect'))->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

it('authenticates registered user and redirects directly to configured home screen contract', function () {
    /** @var \Tests\TestCase $this */
    $lobbyUnit = \Idei\Usim\Models\UsimUnit::firstOrCreate(['slug' => 'lobby']);
    $roleRegistered = \Spatie\Permission\Models\Role::findOrCreate('registered', 'web');

    $user = User::factory()->create([
        'name' => 'Registered Tester',
        'email' => 'regtester@example.com',
        'password' => bcrypt('password123'),
    ]);
    $user->usimUnits()->sync([$lobbyUnit->id]);

    setPermissionsTeamId($lobbyUnit->id);
    $user->assignRole($roleRegistered);

    // Explicitly reset permissions team ID to simulate a fresh incoming request
    setPermissionsTeamId(null);

    $uiResponse = getScreenJson($this, Login::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => 'regtester@example.com',
            'login_password' => 'password123',
        ],
    ]);

    /** @var class-string<\Idei\Usim\Screen> $expectedScreen */
    $expectedScreen = config('usim.roles.registered.home_screen', Home::class);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');
    expect($response->json('navigate.url') ?? $response->json('redirect'))->toBe($expectedScreen::getRoutePath());
    $this->assertAuthenticatedAs($user);
});

it('redirects to the role screen with highest priority when user has multiple roles', function () {
    /** @var \Tests\TestCase $this */
    $mainUnit = \Idei\Usim\Models\UsimUnit::firstOrCreate(['slug' => 'main']);
    $roleRegistered = \Spatie\Permission\Models\Role::findOrCreate('registered', 'web');
    $roleAdmin = \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');

    $user = User::factory()->create([
        'name' => 'Multi Role User',
        'email' => 'multirole@example.com',
        'password' => bcrypt('password123'),
    ]);
    $user->usimUnits()->sync([$mainUnit->id]);

    setPermissionsTeamId($mainUnit->id);
    $user->assignRole($roleRegistered);
    $user->assignRole($roleAdmin);

    // Reset permissions team ID
    setPermissionsTeamId(null);

    $uiResponse = getScreenJson($this, Login::class);
    $uiResponse->assertOk();
    $componentId = serviceRootComponentId($uiResponse->json());

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => 'multirole@example.com',
            'login_password' => 'password123',
        ],
    ]);

    /** @var class-string<\Idei\Usim\Screen> $expectedScreen */
    $expectedScreen = config('usim.roles.admin.home_screen', Home::class);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');
    // Admin has higher precedence than Registered
    expect($response->json('navigate.url') ?? $response->json('redirect'))->toBe($expectedScreen::getRoutePath());
    $this->assertAuthenticatedAs($user);
});
