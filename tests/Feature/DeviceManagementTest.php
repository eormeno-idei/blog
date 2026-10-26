<?php

// @usim: feature="admin", type="test"

use App\Services\Device\DeviceService;
use App\Services\User\UsimUserService;
use App\UI\Screens\Admin\AdminDevicePairingScreen;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Device\EditDevice;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Screen;
use Idei\Usim\Support\DevicePairingManager;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;
use Illuminate\Support\Facades\Event;
use Mockery;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
    UsimRole::firstOrCreate(['name' => 'kiosk', 'guard_name' => 'device']);
    UsimRole::firstOrCreate(['name' => 'display', 'guard_name' => 'device']);
});

it('creates, updates, shares, makes institutional, lists, and deletes devices via DeviceService', function () {
    $service = app(DeviceService::class);

    $opsA = UsimUnit::firstOrCreate(['slug' => 'ops_a'], ['type' => 'operational']);
    $opsB = UsimUnit::firstOrCreate(['slug' => 'ops_b'], ['type' => 'operational']);

    $device = $service->createDevice([
        'name' => 'Lobby Kiosk',
        'specs' => ['resolution' => '1080p'],
        'unit_id' => $opsA->id,
        'roles' => ['kiosk'],
    ]);

    expect($device->name)->toBe('Lobby Kiosk')
        ->and($device->usimUnits->pluck('id')->all())->toContain($opsA->id)
        ->and($device->isPublic())->toBeFalse()
        ->and($device->isShared())->toBeFalse();

    // Update non-existent returns null
    expect($service->updateDevice(99999, ['name' => 'Ghost']))->toBeNull();

    // Update existing device with unit_ids and roles
    $updated = $service->updateDevice($device->id, [
        'name' => 'Updated Kiosk',
        'specs' => ['resolution' => '4K'],
        'unit_ids' => [$opsA->id],
        'roles' => ['kiosk', 'display'],
    ]);

    expect($updated?->name)->toBe('Updated Kiosk')
        ->and($updated?->specs)->toBe(['resolution' => '4K']);

    // Share device with second operational unit
    expect($service->shareDevice(99999, $opsB->id))->toBeNull();
    $shared = $service->shareDevice($device->id, [$opsB->id]);
    expect($shared?->usimUnits->pluck('id')->all())->toContain($opsA->id, $opsB->id)
        ->and($shared?->isShared())->toBeTrue();

    // Make institutional when main unit does not exist vs when it exists
    UsimUnit::where('slug', 'main')->delete();
    expect($service->makeDeviceInstitutional($device->id))->toBeNull();

    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
    $institutional = $service->makeDeviceInstitutional($device->id);
    expect($institutional?->usimUnits->pluck('id')->all())->toContain($mainUnit->id)
        ->and($institutional?->isPublic())->toBeTrue();

    expect($service->getAllDevices())->toHaveCount(1);

    expect($service->deleteDevice(99999))->toBeFalse()
        ->and($service->deleteDevice($device->id))->toBeTrue()
        ->and($service->getDevice($device->id))->toBeNull();
});

it('pairs, JIT-creates, and unpairs devices via DeviceService', function () {
    $opsUnit = UsimUnit::firstOrCreate(['slug' => 'ops'], ['type' => 'operational']);

    $pairingManager = Mockery::mock(DevicePairingManager::class);
    $pairingManager->shouldReceive('approve')
        ->with('1234', Mockery::any())
        ->andReturn(true);
    $pairingManager->shouldReceive('approve')
        ->with('0000', Mockery::any())
        ->andReturn(false);
    $pairingManager->shouldReceive('isValidPin')
        ->with('1234')
        ->andReturn(true);
    $pairingManager->shouldReceive('isValidPin')
        ->with('9999')
        ->andReturn(false);

    $service = new DeviceService($pairingManager);
    $device = $service->createDevice([
        'name' => 'Pairable TV',
        'unit_ids' => [$opsUnit->id],
        'roles' => 'kiosk',
    ]);

    // Invalid PIN length
    expect($service->pairDevice($device->id, '12')['success'])->toBeFalse();
    expect($service->pairAndCreateDevice('12', ['name' => 'X'])['success'])->toBeFalse();

    // Non-existent device
    expect($service->pairDevice(99999, '1234')['success'])->toBeFalse();

    // Invalid PIN rejected by manager
    expect($service->pairDevice($device->id, '0000')['success'])->toBeFalse();
    expect($service->pairAndCreateDevice('9999', ['name' => 'X'])['success'])->toBeFalse();

    // Valid pair
    $paired = $service->pairDevice($device->id, '1234');
    expect($paired['success'])->toBeTrue();
    expect($device->fresh()->isPaired())->toBeTrue();

    // Unpair
    expect($service->unpairDevice(99999))->toBeFalse()
        ->and($service->unpairDevice($device->id))->toBeTrue()
        ->and($device->fresh()->isPaired())->toBeFalse();

    // JIT pairAndCreateDevice success
    $jit = $service->pairAndCreateDevice('1234', [
        'name' => 'JIT Screen',
        'unit_ids' => [$opsUnit->id],
        'roles' => ['kiosk'],
    ]);
    expect($jit['success'])->toBeTrue()
        ->and($jit['device']->isPaired())->toBeTrue();
});

it('renders EditDevice in create and edit modes and handles save, pair/unpair, and delete actions', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    Event::fake([UsimEvent::class]);

    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
    $opsUnit = UsimUnit::firstOrCreate(['slug' => 'ops'], ['type' => 'operational']);

    $caller = Screen::make(UsersManager::class);

    // 1. Open in create mode
    $createModal = Screen::openAsModal(
        params: [],
        caller: $caller,
        screenClass: EditDevice::class
    );
    $createHarness = ScreenTestHarness::for($createModal);
    $createHarness
        ->assertHasComponent('device_name')
        ->assertHasComponent('device_units')
        ->assertHasComponent('device_roles')
        ->assertHasComponent('btn_save_device');

    // Validation errors on empty name or empty roles
    $createHarness->click('btn_save_device', [
        'device_name' => '   ',
        'device_roles' => ['kiosk'],
    ])->assertToast(null, 'error');

    $createHarness->click('btn_save_device', [
        'device_name' => 'New Screen',
        'device_roles' => [],
    ])->assertToast(null, 'error');

    // Create device via form submit
    $createHarness->click('btn_save_device', [
        'device_name' => 'Created Screen',
        'device_units' => [(string) $opsUnit->id],
        'device_roles' => ['kiosk'],
    ])->assertModalClosed();

    $device = app(DeviceService::class)->getAllDevices()->firstWhere('name', 'Created Screen');
    expect($device)->not->toBeNull();
    Event::assertDispatched(UsimEvent::class, fn (UsimEvent $e) => $e->eventName === 'device_created');

    // 2. Open in edit mode with device ID
    $editModal = Screen::openAsModal(
        params: ['device' => ['id' => $device->id]],
        caller: $caller,
        screenClass: EditDevice::class
    );
    /** @var Screen $editModal */
    $editHarness = ScreenTestHarness::for($editModal);
    $editHarness
        ->assertHasComponent('device_status_banner')
        ->assertHasComponent('btn_pair_unpair_device')
        ->assertHasComponent('btn_delete_device');

    // Update device
    $editHarness->click('btn_save_device', [
        'device_id' => $device->id,
        'device_name' => 'Renamed Screen',
        'unit_id' => $mainUnit->id,
        'device_roles' => 'display',
    ])->assertModalClosed();

    expect($device->fresh()->name)->toBe('Renamed Screen');
    Event::assertDispatched(UsimEvent::class, fn (UsimEvent $e) => $e->eventName === 'device_updated');

    // Pair / Unpair and UI refresh callbacks
    $editModal->onPairUnpairDevice(['is_paired' => false, 'device_id' => $device->id]);
    $editHarness->assertModal(AdminDevicePairingScreen::class);

    $editModal->onUnpairDevice(['device_id' => null]);
    $editModal->onPairUnpairDevice(['is_paired' => true, 'device_id' => $device->id]);
    Event::assertDispatched(UsimEvent::class, fn (UsimEvent $e) => $e->eventName === 'device_unpaired');

    $editModal->onDevicePaired(['device' => $device, 'message' => 'Paired!']);
    $editModal->onDeviceUnpaired(['device_id' => $device->id, 'message' => 'Unpaired!']);
    $editModal->onCloseModal([]);

    // Delete flow
    $editModal->onDeleteDevice(['device_id' => null]);
    $editModal->onDeleteDevice(['device_id' => 99999]);
    $editModal->onDeleteDevice(['device_id' => $device->id]);

    $editModal->onConfirmDeleteDevice(['device_id' => null]);
    $editModal->onConfirmDeleteDevice(['device_id' => $device->id]);

    expect(app(DeviceService::class)->getDevice($device->id))->toBeNull();
    Event::assertDispatched(UsimEvent::class, fn (UsimEvent $e) => $e->eventName === 'device_deleted');
});
