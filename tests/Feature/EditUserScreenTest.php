<?php

// @usim: feature="admin", type="test"

use App\Models\User;
use App\Services\User\UsimUserService;
use App\UI\Screens\Admin\EditUser;
use App\UI\Screens\Admin\UsersManager;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Modals\ConfirmDialog;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Screen;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
});

it('renders EditUser standalone with user model or fallback to authenticated user', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $admin->forceFill(['email_verified_at' => now()])->save();
    $this->actingAs($admin);

    $target = User::factory()->unverified()->create([
        'name' => 'Target User',
        'email' => 'target@example.com',
    ]);

    // Render passing User model instance
    $harness = ScreenTestHarness::for(EditUser::class)
        ->render(['user' => $target], shouldReset: true);

    $harness
        ->assertHasComponent('name')
        ->assertHasComponent('email')
        ->assertHasComponent('roles')
        ->assertHasComponent('send_reset_email')
        ->assertHasComponent('send_verification_email')
        ->assertHasComponent('btn_submit_register')
        ->assertHasComponent('btn_delete_user');

    // Render without params falls back to authenticated user (verified email hides send_verification_email)
    $fallbackHarness = ScreenTestHarness::for(EditUser::class)
        ->render([], shouldReset: true);

    $fallbackHarness->assertHasComponent('name');
    expect($fallbackHarness->findComponent('send_verification_email'))->toBeNull();
});

it('renders EditUser modal with lobby banner, active unit, and other unit memberships', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    UsimUnit::firstOrCreate(['slug' => 'ops'], ['type' => 'operational']);
    UsimUnit::firstOrCreate(['slug' => 'sales'], ['type' => 'operational']);

    $userPayload = [
        'id' => 55,
        'name' => 'Lobby User',
        'email' => 'lobby@example.com',
        'email_verified_at' => null,
        'is_in_lobby' => true,
        'has_operational_units' => true,
        'operational_units' => [
            ['id' => 10, 'slug' => 'ops', 'name' => 'Operations'],
        ],
        'units_with_roles' => [
            'ops' => ['registered', 'publisher'],
            'sales' => ['publisher'],
            'lobby' => ['registered'],
        ],
    ];

    $caller = Screen::make(UsersManager::class);
    $modal = Screen::openAsModal(
        params: ['user' => $userPayload],
        caller: $caller,
        screenClass: EditUser::class
    );

    $harness = ScreenTestHarness::for($modal);

    $harness
        ->assertHasComponent('btn_close_modal')
        ->assertHasComponent('lobby_banner')
        ->assertHasComponent('active_unit_badge')
        ->assertHasComponent('target_unit')
        ->assertHasComponent('other_units_info');

    // Close modal via close button
    $harness->click('btn_close_modal')->assertModalClosed();
});

it('validates user_id and updates user from EditUser modal', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    Event::fake([UsimEvent::class]);

    $target = User::factory()->create([
        'name' => 'Before Edit',
        'email' => 'before@example.com',
    ]);

    $caller = Screen::make(UsersManager::class);
    $modal = Screen::openAsModal(
        params: ['user_id' => $target->id],
        caller: $caller,
        screenClass: EditUser::class
    );

    $harness = ScreenTestHarness::for($modal);

    // 1. Missing user_id shows error toast
    $harness->click('btn_submit_register', ['user_id' => 0])
        ->assertToast(null, 'error');

    // 2. Non-existent user_id shows error toast
    $harness->click('btn_submit_register', ['user_id' => 99999])
        ->assertToast(null, 'error');

    // 3. Invalid email triggers validation error from UserService
    $harness->click('btn_submit_register', [
        'user_id' => $target->id,
        'name' => '',
    ])->assertToast(null, 'error');

    // 4. Valid update succeeds, dispatches user_updated, and closes modal
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
    $harness->click('btn_submit_register', [
        'user_id' => $target->id,
        'name' => 'After Edit',
        'email' => 'after@example.com',
        'roles' => ['publisher'],
        'target_unit' => (string) $mainUnit->id,
    ])
        ->assertToast(null, 'success')
        ->assertModalClosed();

    expect($target->fresh()->name)->toBe('After Edit')
        ->and($target->fresh()->email)->toBe('after@example.com');

    Event::assertDispatched(UsimEvent::class, fn (UsimEvent $e) => $e->eventName === 'user_updated');
});

it('opens confirm dialog on delete and deletes user on confirmation', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    Event::fake([UsimEvent::class]);

    $target = User::factory()->create(['name' => 'Delete Me']);

    $caller = Screen::make(UsersManager::class);
    $modal = Screen::openAsModal(
        params: ['user_id' => $target->id],
        caller: $caller,
        screenClass: EditUser::class
    );

    $harness = ScreenTestHarness::for($modal);

    // Invalid delete attempts
    $harness->click('btn_delete_user', ['user_id' => 0])->assertToast(null, 'error');
    $harness->click('btn_delete_user', ['user_id' => 99999])->assertToast(null, 'error');

    // Valid delete opens ConfirmDialog
    $harness->click('btn_delete_user', ['user_id' => $target->id])
        ->assertModal(ConfirmDialog::class);

    // Confirm deletion directly on EditUser screen
    $modal->onConfirmDeleteUser(['user_id' => 0]);
    $modal->onConfirmDeleteUser(['user_id' => 99999]);
    $modal->onConfirmDeleteUser(['user_id' => $target->id]);

    expect(User::find($target->id))->toBeNull();
    Event::assertDispatched(UsimEvent::class, fn (UsimEvent $e) => $e->eventName === 'user_deleted');
});
