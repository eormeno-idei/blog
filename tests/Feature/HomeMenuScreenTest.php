<?php

// @usim: feature="core", type="test"

use App\Models\User;
use App\Services\User\UsimUserService;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Profile;
use App\UI\Screens\Auth\Register;
use App\UI\Screens\Home;
use App\UI\Screens\Menu;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
});

it('renders home screen with expected core components for guests', function () {
    Auth::logout();

    $harness = ScreenTestHarness::for(Home::class)
        ->render(shouldReset: true);

    $harness
        ->assertHasComponent('home_wrapper')
        ->assertHasComponent('home_header')
        ->assertHasComponent('lbl_home_title')
        ->assertHasComponent('lbl_home_subtitle')
        ->assertHasComponent('btn_home_login')
        ->assertHasComponent('btn_home_register')
        ->assertHasComponent('posts_list_container')
        ->assertComponentText('lbl_home_title', '📰 Últimos Posts');
});

it('renders home screen with publish and manage users actions for admin', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    $harness = ScreenTestHarness::for(Home::class)
        ->render(shouldReset: true);

    $harness
        ->assertHasComponent('btn_go_publish')
        ->assertHasComponent('btn_go_users');

    expect($harness->findComponent('btn_home_login'))->toBeNull()
        ->and($harness->findComponent('btn_home_register'))->toBeNull();
});

it('shows waiting room message on home screen for user with only registered role in lobby', function () {
    $defaultRegisteringRole = config('usim.default_registering_role', 'registered');
    $registeredUser = app(UsimUserService::class)->provisionFromConfig($defaultRegisteringRole);
    $this->actingAs($registeredUser);

    $harness = ScreenTestHarness::for(Home::class)
        ->render(shouldReset: true);

    $harness->assertComponentText(
        'lbl_registered_message',
        "{$registeredUser->name}, aún estás en sala de espera."
    );
});

it('opens Login and Register modals from Home guest buttons', function () {
    Auth::logout();

    $loginHarness = ScreenTestHarness::for(Home::class)
        ->render(shouldReset: true)
        ->click('btn_home_login');

    $loginHarness->assertModal(Login::class);

    $registerHarness = ScreenTestHarness::for(Home::class)
        ->render(shouldReset: true)
        ->click('btn_home_register');

    $registerHarness->assertModal(Register::class);
});

it('returns menu screen for guests with settings trigger and register option', function () {
    Auth::logout();

    /** @var TestCase $this */
    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);

    $mainMenu = $ui->component('main_menu')->data();
    $userMenu = $ui->component('user_menu')->data();
    $themeToggle = $ui->component('theme_toggle')->data();

    expect(menuItemsContainLabel($mainMenu['items'] ?? [], Home::getMenuLabel()))->toBeTrue();
    expect(menuItemsContainLabel($mainMenu['items'] ?? [], t('screens.menu.items.about')))->toBeTrue();

    expect($userMenu['type'] ?? null)->toBe('menudropdown');
    expect($userMenu['trigger']['label'] ?? null)->toBe('⚙️');
    expect(menuItemsContainLabel($userMenu['items'] ?? [], Register::getMenuLabel()))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screens.menu.items.logout')))->toBeFalse();
    expect($themeToggle['icon_color'] ?? null)->toBe('var(--usim-menu-trigger-text)');

    $ui->assertNoIssues();
});

it('returns menu screen for authenticated users with user trigger and logout option', function () {
    /** @var TestCase $this */
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
    expect(menuItemsContainLabel($userMenu['items'] ?? [], Register::getMenuLabel()))->toBeFalse();

    $ui->assertNoIssues();
});

it('shows profile, logout and admin dashboard items after admin login', function () {
    /** @var TestCase $this */
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
    $defaultRegisteringRole = config('usim.default_registering_role', 'registered');
    /** @var TestCase $this */
    $this->loginAs($defaultRegisteringRole);

    $ui = uiScenario($this, Menu::class, ['parent' => 'menu']);
    $mainMenu = $ui->component('main_menu')->data();
    $userMenu = $ui->component('user_menu')->data();

    expect(menuItemsContainLabel($mainMenu['items'] ?? [], UsersManager::getMenuLabel()))->toBeFalse();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], Profile::getMenuLabel()))->toBeTrue();
    expect(menuItemsContainLabel($userMenu['items'] ?? [], t('screens.menu.items.logout')))->toBeTrue();

    $ui->assertNoIssues();
});
