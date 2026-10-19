<?php

use App\Models\User;
use App\Services\User\UserService;
use App\Services\User\UsimUserService;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
});

it('finds and gets user details or returns error when user does not exist', function () {
    $service = app(UserService::class);

    expect($service->findUser(99999))->toBeNull();

    $notFound = $service->getUser(99999);
    expect($notFound['status'])->toBe('error')
        ->and($notFound['errors'])->toHaveKey('user_id');

    UsimUnit::firstOrCreate(['slug' => 'ops'], ['type' => 'operational']);

    $user = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);
    expect($service->findUser($user->id)?->id)->toBe($user->id);

    $found = $service->getUser($user->id);
    expect($found['status'])->toBe('success')
        ->and($found['data']['email'])->toBe('test@example.com')
        ->and($found['data']['has_operational_units'])->toBeTrue()
        ->and($found['data']['operational_units'])->not->toBeEmpty();
});

it('validates invalid payloads in updateUser', function (array $payload, string $expectedField) {
    $service = app(UserService::class);
    $user = User::factory()->create();
    $otherUser = User::factory()->create(['email' => 'taken@example.com']);

    $result = $service->updateUser($user, $payload);

    expect($result['status'])->toBe('error')
        ->and($result['errors'])->toHaveKey($expectedField);
})->with([
    'empty name' => [['name' => '   '], 'name'],
    'null email' => [['email' => null], 'email'],
    'empty password' => [['password' => ''], 'password'],
    'missing password confirmation' => [['password' => 'secret1234'], 'password_confirmation'],
    'empty roles array' => [['roles' => []], 'roles'],
    'invalid role item' => [['roles' => ['  ']], 'roles'],
    'name too long' => [['name' => str_repeat('a', 256)], 'name'],
    'invalid email format' => [['email' => 'not-an-email'], 'email'],
    'duplicate email' => [['email' => 'taken@example.com'], 'email'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'mismatched password confirmation' => [['password' => 'secret1234', 'password_confirmation' => 'different1234'], 'password'],
    'non-existent role' => [['roles' => ['non_existent_role']], 'roles'],
]);

it('updates user profile fields, sends notifications, and dispatches updated_profile event for authenticated user', function () {
    Notification::fake();
    Event::fake([UsimEvent::class]);

    $service = app(UserService::class);
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user);

    $result = $service->updateUser($user, [
        'name' => '  New Name  ',
        'email' => 'new@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
        'send_reset_email' => true,
        'send_verification_email' => true,
    ]);

    expect($result['status'])->toBe('success')
        ->and($result['data']['name'])->toBe('New Name')
        ->and($result['data']['email'])->toBe('new@example.com');

    $user->refresh();
    expect(Hash::check('newpassword123', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->toBeNull();

    Event::assertDispatched(UsimEvent::class);
});

it('transitions lobby user to operational unit and prevents removing own admin role', function () {
    $service = app(UserService::class);

    $lobbyUnit = UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
    $opsUnit = UsimUnit::firstOrCreate(['slug' => 'ops_dept'], ['type' => 'operational']);

    $lobbyUser = User::factory()->create();
    $lobbyUser->usimUnits()->sync([$lobbyUnit->id]);
    setPermissionsTeamId($lobbyUnit->id);
    $lobbyUser->assignRole(config('usim.default_registering_role', 'registered'));

    $syncResult = $service->updateUser($lobbyUser, [
        'roles' => ['admin'],
        'target_unit' => 'ops_dept',
    ]);

    expect($syncResult['status'])->toBe('success');
    expect($lobbyUser->fresh()->usimUnits->pluck('slug')->all())->toContain('ops_dept')
        ->not->toContain('lobby');

    // Prevent authenticated admin from removing own admin role
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    $selfDemote = $service->updateUser($admin, [
        'roles' => [config('usim.default_registering_role', 'registered')],
    ]);

    expect($selfDemote['status'])->toBe('error')
        ->and($selfDemote['errors'])->toHaveKey('roles');
});

it('syncs roles when user is not in lobby with explicit or fallback unit', function () {
    $service = app(UserService::class);
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);

    $user = User::factory()->create();
    $user->usimUnits()->sync([$mainUnit->id]);

    $resExplicit = $service->updateUser($user, [
        'roles' => ['admin'],
        'target_unit' => $mainUnit->id,
    ]);
    expect($resExplicit['status'])->toBe('success');

    $resFallback = $service->updateUser($user, [
        'roles' => ['admin'],
    ]);
    expect($resFallback['status'])->toBe('success');
});

it('deletes another user and prevents deleting the currently authenticated user', function () {
    $service = app(UserService::class);
    $authUser = User::factory()->create();
    $targetUser = User::factory()->create(['name' => 'To Delete']);

    $this->actingAs($authUser);

    $selfDelete = $service->deleteUser($authUser);
    expect($selfDelete['status'])->toBe('error');

    $deleteResult = $service->deleteUser($targetUser);
    expect($deleteResult['status'])->toBe('success')
        ->and(User::find($targetUser->id))->toBeNull();
});

it('lists and counts users with search and sorting options', function () {
    $service = app(UserService::class);
    User::factory()->create(['name' => 'Alice Admin', 'email' => 'alice@example.com']);
    User::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@example.com']);

    expect($service->countUsers(''))->toBeGreaterThanOrEqual(2)
        ->and($service->countUsers('Alice'))->toBe(1);

    $listDefault = $service->getUsersList(['search' => 'Alice', 'per_page' => 10]);
    expect($listDefault['status'])->toBe('success')
        ->and($listDefault['data']['users'])->toHaveCount(1);

    $listByRoles = $service->getUsersList(['search' => '', 'sort_by' => 'roles', 'sort_direction' => 'asc']);
    expect($listByRoles['status'])->toBe('success');
});

it('verifies user email and handles missing user, invalid hash, and already verified states', function () {
    Event::fake([Verified::class, UsimEvent::class]);
    $service = app(UserService::class);

    expect($service->verifyEmail(99999, 'bad-hash')['status'])->toBe('error');

    $unverified = User::factory()->unverified()->create(['email' => 'verify@example.com']);
    $validHash = sha1($unverified->getEmailForVerification());

    expect($service->verifyEmail($unverified->id, 'invalid-hash')['status'])->toBe('invalid');

    $verifiedResult = $service->verifyEmail($unverified->id, $validHash);
    expect($verifiedResult['status'])->toBe('verified')
        ->and($verifiedResult['success'])->toBeTrue();

    Event::assertDispatched(Verified::class);
    Event::assertDispatched(UsimEvent::class);

    $alreadyResult = $service->verifyEmail($unverified->id, $validHash);
    expect($alreadyResult['status'])->toBe('already_verified');
});
