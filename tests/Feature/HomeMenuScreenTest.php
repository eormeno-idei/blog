<?php
// @usim: feature="core", type="test"

use App\Models\User;
use App\Services\User\UsimUserService;
use App\UI\Screens\Admin\TranslateManager;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Profile;
use App\UI\Screens\Home;
use App\UI\Screens\Menu;
use Idei\Usim\Models\UsimUnit;

beforeEach(function () {
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
});

it('returns home screen with expected core components', function () {
    $ui = uiScenario($this, Home::class, ['reset' => true]);

    $welcomeUsim = $ui->component('welcome_usim');

    $welcomeUsim->expect('type')->toBe('label');
    expect($welcomeUsim->data()['html'] ?? null)->not->toBeNull();

    $ui->assertNoIssues();
});

it('home screen renders the welcome-usim html fragment', function () {
    $ui = uiScenario($this, Home::class, ['reset' => true]);

    $data = $ui->component('welcome_usim')->data();

    expect($data['type'] ?? null)->toBe('label');
    expect($data['html'] ?? '')->toContain('class="wf"');

    $ui->assertNoIssues();
});

it('home screen fragment contains expected landing sections', function () {
    $ui = uiScenario($this, Home::class, ['reset' => true]);

    $html = $ui->component('welcome_usim')->data()['html'] ?? '';

    expect($html)->toContain('class="hero"');
    expect($html)->toContain('class="wf"');

    $ui->assertNoIssues();
});

it('home fragment theme follows global document theme contract', function () {
    $ui = uiScenario($this, Home::class, ['reset' => true]);

    $html = $ui->component('welcome_usim')->data()['html'] ?? '';

    expect($html)->toContain('class="wf"');
    expect($html)->not->toContain('class="wf" data-theme="dark"');
    expect($html)->toContain('html[data-theme="light"] .wf');
    expect($html)->toContain('body[data-theme="light"] .wf');

    $ui->assertNoIssues();
});

it('home screen fragment is non-empty regardless of locale', function () {
    $originalLocale = app()->getLocale();
    app()->setLocale('es');

    $ui = uiScenario($this, Home::class, ['reset' => true]);
    $html = $ui->component('welcome_usim')->data()['html'] ?? '';

    expect($html)->not->toBeEmpty();

    app()->setLocale($originalLocale);

    $ui->assertNoIssues();
});

it('returns menu screen for guests with settings trigger and register option', function () {
    $originalLocale = app()->getLocale();

    foreach (['es', 'en'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
        $storage = json_decode($ui->opaqueUsim(), true) ?: [];
        $menuLang = (string) ($storage['store_lang'] ?? app()->getLocale());

        $mainMenu = $ui->component('main_menu')->data();
        $userMenu = $ui->component('user_menu')->data();
        $themeToggle = $ui->component('theme_toggle')->data();
        $homeLabel = t('screens.menu.items.home', [], $menuLang);
        $aboutLabel = t('screens.about.menu_title', [], $menuLang);
        expect(menuItemsContainLabel($mainMenu['items'] ?? [], $homeLabel))->toBeTrue();
        expect(menuItemsContainLabel($mainMenu['items'] ?? [], $aboutLabel))->toBeTrue();

        expect($userMenu['type'] ?? null)->toBe('menudropdown');
        expect($userMenu['trigger']['label'] ?? null)->toBe('⚙️');
        expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screens.menu.items.register', [], $menuLang)))->toBeTrue();
        expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screens.menu.items.logout', [], $menuLang)))->toBeFalse();
        expect($themeToggle['icon_color'] ?? null)->toBe('var(--usim-menu-trigger-text)');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('returns menu screen for authenticated users with user trigger and logout option', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        /** @var \Tests\TestCase $this */
        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'Menu Tester',
        ]);

        $this->actingAs($user);

        $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
        $userMenu = $ui->component('user_menu')->data();

        expect($userMenu['type'] ?? null)->toBe('menudropdown');

        $triggerLabel = (string) ($userMenu['trigger']['label'] ?? '');
        expect($triggerLabel)->toContain('Menu Tester');

        expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screens.menu.items.logout')))->toBeTrue();
        expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screens.menu.items.register')))->toBeFalse();

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('shows profile, logout and admin dashboard items after admin login', function () {
    /** @var \Tests\TestCase $this */
    $this->loginAs('root');

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $mainMenu = $ui->component('main_menu')->data();
    $userMenu = $ui->component('user_menu')->data();

    expect(menuItemsContainLabel($mainMenu['items'] ?? [], UsersManager::getMenuLabel()))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], Profile::getMenuLabel()))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screens.menu.items.logout')))->toBeTrue();

    $ui->assertNoIssues();
});

it('shows profile/logout and hides admin dashboard after regular user login', function () {
    $defaultRegisteringRole = config('usim.default_registering_role', 'user');
    /** @var \Tests\TestCase $this */
    $this->loginAs($defaultRegisteringRole);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $mainMenu = $ui->component('main_menu')->data();
    $userMenu = $ui->component('user_menu')->data();

    expect(menuItemsContainLabel($mainMenu['items'] ?? [], UsersManager::getMenuLabel()))->toBeFalse();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], Profile::getMenuLabel()))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screens.menu.items.logout')))->toBeTrue();

    $ui->assertNoIssues();
});

it('hides unit dropdown for guests', function () {
    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $data = $ui->component('unit_menu')->data();
    expect($data['visible'] ?? true)->toBeFalse();
    expect($data['items'] ?? [])->toBeEmpty();
    $ui->assertNoIssues();
});

it('hides unit dropdown for authenticated user with no units', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'No Units User']);
    $this->actingAs($user);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $data = $ui->component('unit_menu')->data();
    expect($data['visible'] ?? true)->toBeFalse();
    expect($data['items'] ?? [])->toBeEmpty();
    $ui->assertNoIssues();
});

it('hides unit dropdown for authenticated user with only system unit', function () {
    /** @var \Tests\TestCase $this */
    $unit = UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
    $unit->update(['type' => 'system']);
    $user = User::factory()->create(['name' => 'Single Unit User']);
    $user->usimUnits()->attach($unit->id);
    $this->actingAs($user);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $data = $ui->component('unit_menu')->data();
    expect($data['visible'] ?? true)->toBeFalse();
    expect($data['items'] ?? [])->toBeEmpty();
    $ui->assertNoIssues();
});

it('shows unit dropdown for authenticated user with single operational unit', function () {
    /** @var \Tests\TestCase $this */
    $unit = UsimUnit::firstOrCreate(['slug' => 'idei'], ['type' => 'institute']);
    $unit->update(['type' => 'institute']);
    $user = User::factory()->create(['name' => 'Single Operational Unit User']);
    $user->usimUnits()->attach($unit->id);
    $this->actingAs($user);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $data = $ui->component('unit_menu')->data();
    expect($data['visible'] ?? true)->toBeTrue();
    expect($data['trigger']['label'] ?? '')->toContain('🏢');
    expect(menuItemsContainLabel($data['items'] ?? [], '✓ ' . ($unit->display_name ?: 'Idei')))->toBeTrue();
    $ui->assertNoIssues();
});

it('includes unit dropdown when authenticated user has multiple units', function () {
    /** @var \Tests\TestCase $this */
    $unit1 = UsimUnit::firstOrCreate(['slug' => 'main']);
    $unit1->update(['type' => 'department']);
    $unit2 = UsimUnit::firstOrCreate(['slug' => 'accounting'], ['type' => 'department']);
    $user = User::factory()->create(['name' => 'Multi Unit User']);
    $user->usimUnits()->attach([$unit1->id, $unit2->id]);
    $this->actingAs($user);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $unitMenu = $ui->component('unit_menu');

    expect($unitMenu)->not->toBeNull();
    $data = $unitMenu->data();
    expect($data['type'] ?? null)->toBe('menudropdown');
    expect($data['trigger']['label'] ?? '')->toContain('🏢');
    expect(menuItemsContainLabel($data['items'] ?? [], '✓ ' . ($unit1->display_name ?: 'Main')))->toBeTrue();
    expect(menuItemsContainLabel($data['items'] ?? [], 'Accounting'))->toBeTrue();

    $ui->assertNoIssues();
});

it('allows switching units when user has multiple units', function () {
    /** @var \Tests\TestCase $this */
    $unit1 = UsimUnit::firstOrCreate(['slug' => 'main']);
    $unit1->update(['type' => 'department']);
    $unit2 = UsimUnit::firstOrCreate(['slug' => 'finance'], ['type' => 'department']);
    $user = User::factory()->create(['name' => 'Switch Unit User']);
    $user->usimUnits()->attach([$unit1->id, $unit2->id]);
    $this->actingAs($user);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    expect($ui->component('unit_menu'))->not->toBeNull();

    $ui->action('unit_menu', 'changeUnit', ['unit' => 'finance', 'unit_id' => $unit2->id]);
    expect(\Idei\Usim\Support\UIStateManager::getKeyValue('active_unit'))->toBe('finance');
    expect(getPermissionsTeamId())->toBe($unit2->id);
});

it('allows root to switch to operational unit comunicacion and does not retain store_unit in storage payload', function () {
    /** @var \Tests\TestCase $this */
    $userService = app(UsimUserService::class);
    $rootUser = $userService->provisionFromConfig('root');
    $this->actingAs($rootUser);

    $unit1 = UsimUnit::firstOrCreate(['slug' => 'idei'], ['type' => 'department']);
    $comUnit = UsimUnit::firstOrCreate(['slug' => 'comunicación'], ['type' => 'department']);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $response = $ui->action('unit_menu', 'changeUnit', ['unit' => 'comunicación', 'unit_id' => $comUnit->id]);
    $response->assertOk();

    expect(\Idei\Usim\Support\UIStateManager::getKeyValue('active_unit'))->toBe('comunicación');
    expect(getPermissionsTeamId())->toBe($comUnit->id);

    expect(\Idei\Usim\Support\UIStateManager::getActiveUnit())->toBe('comunicación');

    $storagePayload = $response->json('storage.usim-framework');
    if ($storagePayload !== null) {
        $decoded = json_decode($storagePayload, true);
        expect($decoded['store_unit'] ?? null)->toBeNull();
    }
});

it('redirects to role default home screen on unit change based on unit roles', function () {
    /** @var \Tests\TestCase $this */
    $userService = app(UsimUserService::class);
    $oafaUnit = UsimUnit::firstOrCreate(['slug' => 'oafa'], ['type' => 'institute']);
    $ideiUnit = UsimUnit::firstOrCreate(['slug' => 'idei'], ['type' => 'institute']);

    if (config('usim.users.admin')) {
        $user = $userService->provisionFromConfig('admin');
    } else {
        $user = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@example.com']);
        $user->usimUnits()->sync([$oafaUnit->id, $ideiUnit->id]);
        $roleAdmin = \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $roleTranslator = \Spatie\Permission\Models\Role::findOrCreate('translator', 'web');
        setPermissionsTeamId($oafaUnit->id);
        $user->assignRole($roleAdmin);
        setPermissionsTeamId($ideiUnit->id);
        $user->assignRole($roleTranslator);
    }
    $this->actingAs($user);

    // Start in OAFA
    setPermissionsTeamId($oafaUnit->id);
    \Idei\Usim\Support\UIStateManager::storeKeyValue('active_unit', 'oafa');

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);

    // Switch to IDEI: role in IDEI is translator, so it should navigate to TranslateManager
    $response = $ui->action('unit_menu', 'changeUnit', ['unit' => 'idei', 'unit_id' => $ideiUnit->id]);
    $response->assertOk();
    $targetPath = $response->json('navigate.url') ?? $response->json('redirect');
    expect($targetPath)->toBe(TranslateManager::getRoutePath());
    expect(\Idei\Usim\Support\UIStateManager::getKeyValue('active_unit'))->toBe('idei');
    expect(getPermissionsTeamId())->toBe($ideiUnit->id);

    // Switch back to OAFA: role in OAFA is admin, so it should navigate to UsersManager
    $uiOafa = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $responseOafa = $uiOafa->action('unit_menu', 'changeUnit', ['unit' => 'oafa', 'unit_id' => $oafaUnit->id]);
    $responseOafa->assertOk();
    $targetPathOafa = $responseOafa->json('navigate.url') ?? $responseOafa->json('redirect');
    expect($targetPathOafa)->toBe(UsersManager::getRoutePath());
    expect(\Idei\Usim\Support\UIStateManager::getKeyValue('active_unit'))->toBe('oafa');
    expect(getPermissionsTeamId())->toBe($oafaUnit->id);
});

it('redirects to highest priority role home screen when unit has multiple roles', function () {
    /** @var \Tests\TestCase $this */
    $userService = app(UsimUserService::class);
    $ideiUnit = UsimUnit::firstOrCreate(['slug' => 'idei'], ['type' => 'institute']);

    if (config('usim.users.test')) {
        $user = $userService->provisionFromConfig('test'); // has idei => ['admin', 'translator']
    } else {
        $user = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);
        $user->usimUnits()->sync([$ideiUnit->id]);
        $roleAdmin = \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $roleTranslator = \Spatie\Permission\Models\Role::findOrCreate('translator', 'web');
        setPermissionsTeamId($ideiUnit->id);
        $user->assignRole($roleAdmin);
        $user->assignRole($roleTranslator);
    }
    $this->actingAs($user);

    $otherUnit = UsimUnit::firstOrCreate(['slug' => 'other_dept'], ['type' => 'department']);
    $user->usimUnits()->syncWithoutDetaching([$otherUnit->id]);

    // Start in other_dept
    setPermissionsTeamId($otherUnit->id);
    \Idei\Usim\Support\UIStateManager::storeKeyValue('active_unit', 'other_dept');

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);

    // Switch to IDEI: user has ['admin', 'translator'] in idei. Admin priority (1) > Translator priority (2)
    $response = $ui->action('unit_menu', 'changeUnit', ['unit' => 'idei', 'unit_id' => $ideiUnit->id]);
    $response->assertOk();
    $targetPath = $response->json('navigate.url') ?? $response->json('redirect');
    expect($targetPath)->toBe(UsersManager::getRoutePath());
});
