<?php

// @usim: feature="core", type="test"

use App\Contracts\PostServiceInterface;
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

it('renders home screen with unified CardGrid header and guest actions', function () {
    Auth::logout();

    $harness = ScreenTestHarness::for(Home::class)
        ->render(shouldReset: true);

    $harness
        ->assertHasComponent('home_wrapper')
        ->assertHasComponent('posts_grid');

    $grid = $harness->getCardGrid('posts_grid');
    /** @var list<array<string, mixed>> $headerActions */
    $headerActions = is_array($grid->get('header_actions')) ? $grid->get('header_actions') : [];
    $actionNames = collect($headerActions)->pluck('action')->all();

    expect($grid->get('title'))->toBe('📰 Últimos Posts')
        ->and($grid->get('subtitle'))->toBe('Explora las publicaciones y novedades recientes.')
        ->and($grid->get('card_layout'))->toBe('horizontal')
        ->and($grid->get('show_layout_toggle'))->toBeTrue()
        ->and($grid->getPagination()['mode'])->toBe('load_more')
        ->and($actionNames)->toContain('open_login', 'open_register');
});

it('renders home screen with publish and manage users header actions for admin', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    $harness = ScreenTestHarness::for(Home::class)
        ->render(shouldReset: true);

    $grid = $harness->getCardGrid('posts_grid');
    /** @var list<array<string, mixed>> $headerActions */
    $headerActions = is_array($grid->get('header_actions')) ? $grid->get('header_actions') : [];
    $actionNames = collect($headerActions)->pluck('action')->all();

    expect($actionNames)
        ->toContain('go_to_publish', 'go_to_users')
        ->not->toContain('open_login')
        ->not->toContain('open_register');
});

it('shows waiting room message with warning style in CardGrid header for user with only registered role in lobby', function () {
    $defaultRegisteringRole = config('usim.default_registering_role', 'registered');
    $registeredUser = app(UsimUserService::class)->provisionFromConfig($defaultRegisteringRole);
    $this->actingAs($registeredUser);

    $harness = ScreenTestHarness::for(Home::class)
        ->render(shouldReset: true);

    $grid = $harness->getCardGrid('posts_grid');

    expect($grid->get('title'))->toBe("⏳ {$registeredUser->name}, aún estás en sala de espera.")
        ->and($grid->get('title_style'))->toBe('warning');
});

it('opens Login and Register modals from Home guest header actions', function () {
    Auth::logout();

    $loginHarness = ScreenTestHarness::for(Home::class)
        ->render(shouldReset: true)
        ->call('open_login');

    $loginHarness->assertModal(Login::class);

    $registerHarness = ScreenTestHarness::for(Home::class)
        ->render(shouldReset: true)
        ->call('open_register');

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
    expect($themeToggle['action'] ?? null)->toBe('toggleTheme');

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

it('manages post ItemCards inside CardGrid on publish, search, filter, and delete', function () {
    $publisher = app(UsimUserService::class)->provisionFromConfig('publisher');
    $this->actingAs($publisher);

    $postService = app(PostServiceInterface::class);
    $publishedId = $postService->createPost([
        'user_id' => $publisher->id,
        'title' => 'Convocatoria de Becas 2026',
        'content' => 'Información oficial de becas.',
        'status' => 'published',
    ]);

    $harness = ScreenTestHarness::for(Home::class)
        ->render(shouldReset: true);

    $harness
        ->assertHasCard('posts_grid', $publishedId)
        ->assertCardBadge('posts_grid', $publishedId, '🟢 Publicado', 'success');

    $publishedCard = $harness->getCard('posts_grid', $publishedId);
    expect($publishedCard->get('title'))->toBe('Convocatoria de Becas 2026')
        ->and($publishedCard->get('subtitle'))->toBe('Autor: Tú')
        ->and($publishedCard->get('image'))->toStartWith('data:image/svg+xml;base64,');

    // Crear nuevo borrador desde Home -> debe hacer prependCard en card_order 0
    $harness->call('go_to_publish');

    $draft = $postService->getLatestPosts($publisher)
        ->first(fn ($p) => $p->statusValue() === 'draft');
    expect($draft)->not->toBeNull();

    $draftId = (int) $draft?->id;
    $harness
        ->assertHasCard('posts_grid', $draftId)
        ->assertCardBadge('posts_grid', $draftId, '📝 Borrador', 'warning')
        ->assertCardHasAction('posts_grid', $draftId, 'edit_post')
        ->assertCardHasAction('posts_grid', $draftId, 'delete_post');

    expect($harness->getCard('posts_grid', $draftId)->getCardOrder())->toBe(0);

    // Filtrar por estado y buscar usando el evento nativo de CardGrid
    $harness->call('__usim_card_grid:posts_grid:query', [
        'search' => 'Becas',
        'filters' => ['status' => 'published'],
    ]);

    $harness
        ->assertHasCard('posts_grid', $publishedId)
        ->assertMissingCard('posts_grid', $draftId);

    // Limpiar filtros y eliminar el borrador clickeando la acción de tarjeta
    $harness
        ->call('__usim_card_grid:posts_grid:query', [
            'search' => '',
            'filters' => [],
        ])
        ->clickCardAction('posts_grid', $draftId, 'delete_post')
        ->assertMissingCard('posts_grid', $draftId)
        ->assertHasCard('posts_grid', $publishedId);
});

it('renders reviewer actions, reviewer comments, uploaded storage URLs, and guest published badge in Home CardGrid', function () {
    $userService = app(UsimUserService::class);
    $publisher = $userService->provisionFromConfig('publisher');
    $reviewer = $userService->provisionFromConfig('reviewer');
    $postService = app(PostServiceInterface::class);

    $pendingId = $postService->createPost([
        'user_id' => $publisher->id,
        'title' => 'Propuesta Pendiente de Revisión',
        'content' => 'Contenido de prueba',
        'status' => 'pending_review',
    ]);

    $changesId = $postService->createPost([
        'user_id' => $publisher->id,
        'title' => 'Propuesta con Observaciones',
        'content' => 'Contenido inicial',
        'status' => 'pending_review',
    ]);
    $postService->requestChanges($changesId, $reviewer, 'Agregar fecha de cierre');

    $publishedId = $postService->createPost([
        'user_id' => $publisher->id,
        'title' => 'Noticia Publicada',
        'content' => 'Ya visible para invitados',
        'image' => 'portada-noticia.jpg',
        'status' => 'published',
    ]);

    // 1. Revisor ve acción review_post en pending_review y comentario en changes_requested
    $this->actingAs($reviewer);
    $reviewerHarness = ScreenTestHarness::for(Home::class)->render(shouldReset: true);

    $reviewerHarness
        ->assertHasCard('posts_grid', $pendingId)
        ->assertCardBadge('posts_grid', $pendingId, '⏳ En revisión', 'info')
        ->assertCardHasAction('posts_grid', $pendingId, 'review_post')
        ->assertHasCard('posts_grid', $changesId)
        ->assertCardBadge('posts_grid', $changesId, '⚠️ Con observaciones', 'danger');

    expect($reviewerHarness->getCard('posts_grid', $changesId)->get('description'))
        ->toBe('💬 Obs: Agregar fecha de cierre');

    // 2. Publisher ve acción Corregir y observación en changes_requested
    $this->actingAs($publisher);
    $publisherHarness = ScreenTestHarness::for(Home::class)->render(shouldReset: true);

    $publisherHarness
        ->assertHasCard('posts_grid', $changesId)
        ->assertCardHasAction('posts_grid', $changesId, 'edit_post');

    $ownerChangesCard = $publisherHarness->getCard('posts_grid', $changesId);
    /** @var list<array<string, mixed>> $ownerActions */
    $ownerActions = is_array($ownerChangesCard->get('actions')) ? $ownerChangesCard->get('actions') : [];
    $editAction = collect($ownerActions)->firstWhere('action', 'edit_post');
    expect($editAction['label'] ?? null)->toBe('Corregir')
        ->and($ownerChangesCard->get('description'))->toBe('💬 Obs: Agregar fecha de cierre');

    // 3. Invitado solo ve el post publicado con imagen resuelta desde uploads/images y sin acciones
    Auth::logout();
    $guestHarness = ScreenTestHarness::for(Home::class)->render(shouldReset: true);

    $guestHarness
        ->assertHasCard('posts_grid', $publishedId)
        ->assertCardBadge('posts_grid', $publishedId, '🟢 Publicado', 'success')
        ->assertCardMissingAction('posts_grid', $publishedId, 'edit_post')
        ->assertCardMissingAction('posts_grid', $publishedId, 'delete_post')
        ->assertMissingCard('posts_grid', $pendingId)
        ->assertMissingCard('posts_grid', $changesId);

    $publishedJson = $guestHarness->getCard('posts_grid', $publishedId)->toJson();
    $serializedCard = reset($publishedJson);
    expect(is_array($serializedCard) ? ($serializedCard['image'] ?? null) : null)
        ->toContain('/files/uploads/images/portada-noticia.jpg')
        ->and(is_array($serializedCard) ? ($serializedCard['image_fit'] ?? null) : null)
        ->toBe('scale-down');
});
