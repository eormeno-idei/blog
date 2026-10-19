<?php

// @usim: feature="admin", type="test"

use App\Contracts\PostServiceInterface;
use App\Models\User;
use App\Services\Device\DeviceService;
use App\Services\User\UsimUserService;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\EmailVerified;
use App\UI\Screens\Auth\Profile;
use App\UI\Screens\PublishPost;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Screen;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
    UsimRole::firstOrCreate(['name' => 'kiosk', 'guard_name' => 'device']);
});

it('exercises UsersManager device actions, DeviceTableModel formatting, and page switching', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
    $opsA = UsimUnit::firstOrCreate(['slug' => 'ops_a'], ['type' => 'operational']);
    $opsB = UsimUnit::firstOrCreate(['slug' => 'ops_b'], ['type' => 'operational']);

    $deviceService = app(DeviceService::class);
    $publicDevice = $deviceService->createDevice([
        'name' => 'Public TV',
        'unit_ids' => [$mainUnit->id],
        'roles' => ['kiosk'],
    ]);
    $sharedDevice = $deviceService->createDevice([
        'name' => 'Shared TV',
        'unit_ids' => [$opsA->id, $opsB->id],
        'roles' => ['kiosk'],
    ]);
    $publicAndOpsDevice = $deviceService->createDevice([
        'name' => 'Hybrid TV',
        'unit_ids' => [$mainUnit->id, $opsA->id],
        'roles' => [],
    ]);

    /** @var UsersManager $screen */
    $screen = Screen::make(UsersManager::class);
    ScreenTestHarness::for($screen)->render([], shouldReset: true);

    // Switch pages in UsersManager
    $screen->onChangePage(['page' => 'devices']);
    $screen->onChangePage(['page' => 'roles']);
    $screen->onChangePage(['page' => 'users']);

    // ManagesDevicesSection::onPairDeviceClicked (without and with device_id)
    $screen->onPairDeviceClicked([]);
    $screen->onPairDeviceClicked(['device_id' => $publicDevice->id]);

    // ManagesDevicesSection::onSubmitSaveDevice (validation + create + update)
    $screen->onSubmitSaveDevice(['device_name' => '', 'device_roles' => ['kiosk']]);
    $screen->onSubmitSaveDevice(['device_name' => 'Valid Name', 'device_roles' => []]);
    $screen->onSubmitSaveDevice([
        'device_name' => 'Manager Created Device',
        'device_units' => [(string) $opsA->id],
        'device_roles' => ['kiosk'],
    ]);
    $screen->onSubmitSaveDevice([
        'device_id' => $sharedDevice->id,
        'device_name' => 'Manager Updated Device',
        'unit_id' => (string) $opsB->id,
        'device_roles' => 'kiosk',
    ]);

    // ManagesDevicesSection::onSubmitApproveDevicePairing
    $screen->onSubmitApproveDevicePairing(['input_pin' => '12']);
    $screen->onSubmitApproveDevicePairing(['input_pin' => '1234', 'pairing_device_id' => 0]);
    $screen->onSubmitApproveDevicePairing(['input_pin' => '1234', 'pairing_device_id' => $publicDevice->id]);

    expect($sharedDevice->fresh()->name)->toBe('Manager Updated Device')
        ->and($publicAndOpsDevice->id)->toBeGreaterThan(0);
});

it('renders and saves Profile and handles verification and password reset actions', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create([
        'name' => 'Initial Profile Name',
        'email' => 'profile@example.com',
        'profile_image' => 'avatar.png',
    ]);
    $this->actingAs($user);

    /** @var Profile $screen */
    $screen = Screen::make(Profile::class);
    ScreenTestHarness::for($screen)->render([], shouldReset: true);

    // Empty name validation
    $screen->onSaveProfile(['input_name' => '   ']);

    // Valid profile save
    $screen->onSaveProfile(['input_name' => 'Updated Profile Name']);
    expect($user->fresh()->name)->toBe('Updated Profile Name');

    // Resend verification for unverified vs verified user
    $screen->onResendVerification([]);
    $user->forceFill(['email_verified_at' => now()])->save();
    $screen->onResendVerification([]);

    // Request password change link
    $screen->onChangePassword([]);
});

it('verifies email states in EmailVerified screen', function () {
    $user = User::factory()->unverified()->create(['email' => 'checkverify@example.com']);
    $hash = sha1($user->getEmailForVerification());

    // 1. Invalid params
    request()->replace(['id' => 0, 'hash' => '']);
    ScreenTestHarness::for(EmailVerified::class)->render([], shouldReset: true);

    // 2. Expired link
    request()->replace(['id' => $user->id, 'hash' => $hash, 'expires' => now()->subMinute()->timestamp]);
    ScreenTestHarness::for(EmailVerified::class)->render([], shouldReset: true);

    // 3. Valid verification
    request()->replace(['id' => $user->id, 'hash' => $hash, 'expires' => now()->addHour()->timestamp]);
    /** @var EmailVerified $screen */
    $screen = Screen::make(EmailVerified::class);
    ScreenTestHarness::for($screen)->render([], shouldReset: true);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    // 4. Already verified
    ScreenTestHarness::for(EmailVerified::class)->render([], shouldReset: true);

    $screen->onGoToLogin([]);
    $screen->onGoToHome([]);
    $screen->onResendVerification([]);
});

it('validates and publishes posts in PublishPost screen', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    /** @var PublishPost $screen */
    $screen = Screen::make(PublishPost::class);
    ScreenTestHarness::for($screen)->render([], shouldReset: true);

    $postService = app(PostServiceInterface::class);
    $initialCount = $postService->getLatestPosts()->count();

    // Validation errors when title or content is empty
    $screen->onSavePost(['post_title' => '', 'post_content' => '']);
    expect($postService->getLatestPosts()->count())->toBe($initialCount);

    // Successful post creation
    $screen->onSavePost([
        'post_title' => 'My First Post',
        'post_content' => 'Hello world content',
    ]);

    expect($postService->getLatestPosts()->where('title', 'My First Post'))->not->toBeEmpty();

    // Cancel post navigation
    $screen->onCancelPost([]);

    // Unauthenticated guard
    Auth::logout();
    $screen->onSavePost([
        'post_title' => 'Should Not Save',
        'post_content' => 'No auth',
    ]);
    expect($postService->getLatestPosts()->where('title', 'Should Not Save'))->toBeEmpty();
});
