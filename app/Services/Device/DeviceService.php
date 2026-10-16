<?php
// @usim: feature="admin", type="service"
namespace App\Services\Device;

use App\Models\Device;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Support\DevicePairingManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeviceService
{
    public function __construct(
        protected ?DevicePairingManager $pairingManager = null,
    ) {
        $this->pairingManager = $pairingManager ?? app(DevicePairingManager::class);
    }

    /**
     * @param array{
     *     name: string,
     *     roles?: list<string>|string,
     *     unit_id?: int|string|null,
     *     unit_ids?: list<int|string>,
     *     specs?: array<string, mixed>|null
     * } $data
     */
    public function createDevice(array $data): Device
    {
        $device = Device::create([
            'name' => $data['name'],
            'specs' => $data['specs'] ?? null,
        ]);

        /** @var list<int> $unitIds */
        $unitIds = [];
        if (!empty($data['unit_ids'])) {
            $unitIds = array_map(static fn(int|string $unitId): int => (int) $unitId, $data['unit_ids']);
        } elseif (!empty($data['unit_id'])) {
            $unitIds = [(int) $data['unit_id']];
        }

        if (!empty($unitIds)) {
            $device->usimUnits()->sync($unitIds);
        }

        if (!empty($data['roles'])) {
            $roles = is_array($data['roles']) ? $data['roles'] : [$data['roles']];
            $targetUnits = !empty($unitIds) ? $unitIds : [null];

            foreach ($targetUnits as $targetUnitId) {
                if ($targetUnitId !== null && function_exists('setPermissionsTeamId') && function_exists('getPermissionsTeamId')) {
                    $prevTeamId = getPermissionsTeamId();
                    try {
                        setPermissionsTeamId($targetUnitId);
                        $device->syncRoles($roles);
                    } finally {
                        setPermissionsTeamId($prevTeamId);
                    }
                } else {
                    $device->syncRoles($roles);
                }
            }
        }

        return $device;
    }

    /**
     * @param int|string $id
     * @param array{
     *     name?: string,
     *     roles?: list<string>|string,
     *     unit_id?: int|string|null,
     *     unit_ids?: list<int|string>,
     *     specs?: array<string, mixed>|null
     * } $data
     */
    public function updateDevice(int|string $id, array $data): ?Device
    {
        $device = $this->getDevice($id);
        if (!$device) {
            return null;
        }

        if (isset($data['name'])) {
            $device->name = $data['name'];
        }

        if (array_key_exists('specs', $data)) {
            $device->specs = $data['specs'];
        }

        $device->save();

        /** @var list<int>|null $unitIds */
        $unitIds = null;
        if (array_key_exists('unit_ids', $data)) {
            $unitIds = array_map(static fn(int|string $unitId): int => (int) $unitId, $data['unit_ids']);
        } elseif (array_key_exists('unit_id', $data)) {
            $unitIds = $data['unit_id'] !== null ? [(int) $data['unit_id']] : [];
        }

        if ($unitIds !== null) {
            $device->usimUnits()->sync($unitIds);
        }

        if (isset($data['roles'])) {
            $roles = is_array($data['roles']) ? $data['roles'] : [$data['roles']];
            /** @var list<int> $effectiveUnits */
            $effectiveUnits = $unitIds ?? array_map(
                static fn(mixed $unitId): int => is_int($unitId) || is_string($unitId) ? (int) $unitId : 0,
                $device->usimUnits->pluck('id')->all()
            );
            $targetUnits = !empty($effectiveUnits) ? $effectiveUnits : [null];

            foreach ($targetUnits as $targetUnitId) {
                if ($targetUnitId !== null && function_exists('setPermissionsTeamId') && function_exists('getPermissionsTeamId')) {
                    $prevTeamId = getPermissionsTeamId();
                    try {
                        setPermissionsTeamId($targetUnitId);
                        $device->syncRoles($roles);
                    } finally {
                        setPermissionsTeamId($prevTeamId);
                    }
                } else {
                    $device->syncRoles($roles);
                }
            }
        }

        return $device;
    }

    /**
     * Share a device with specified unit(s), replicating its roles in those units.
     *
     * @param int|string $id
     * @param int|string|list<int|string> $unitIds
     * @return Device|null
     */
    public function shareDevice(int|string $id, int|string|array $unitIds): ?Device
    {
        $device = $this->getDevice($id);
        if (!$device) {
            return null;
        }

        $units = is_array($unitIds)
            ? array_map(static fn(int|string $unitId): int => (int) $unitId, $unitIds)
            : [(int) $unitIds];
        $device->usimUnits()->syncWithoutDetaching($units);

        $existingRoles = $device->roles->pluck('name')->filter('is_string')->all();
        if (empty($existingRoles) && $device->relationLoaded('globalRoles')) {
            $existingRoles = $device->globalRoles->pluck('name')->filter('is_string')->all();
        }

        if (!empty($existingRoles) && function_exists('setPermissionsTeamId') && function_exists('getPermissionsTeamId')) {
            $prevTeamId = getPermissionsTeamId();
            try {
                foreach ($units as $uId) {
                    setPermissionsTeamId($uId);
                    $device->assignRole($existingRoles);
                }
            } finally {
                setPermissionsTeamId($prevTeamId);
            }
        }

        return $device;
    }

    /**
     * Promote a device to public / institutional scope by assigning it to 'main'.
     */
    public function makeDeviceInstitutional(int|string $id): ?Device
    {
        $rawMainId = UsimUnit::where('slug', 'main')->value('id');
        if (!is_numeric($rawMainId)) {
            return null;
        }

        return $this->shareDevice($id, (int) $rawMainId);
    }

    public function deleteDevice(int|string $id): bool
    {
        $device = $this->getDevice($id);
        if (!$device) {
            return false;
        }

        $device->tokens()->delete();
        $device->usimUnits()->detach();

        return (bool) $device->delete();
    }

    public function unpairDevice(int|string $id): bool
    {
        $device = $this->getDevice($id);
        if (!$device) {
            return false;
        }

        $device->tokens()->delete();
        $device->device_token = null;
        $device->pairing_pin = null;
        $device->save();

        return true;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function pairDevice(int|string $id, string $pin): array
    {
        $cleanPin = trim($pin);
        if (strlen($cleanPin) !== 4) {
            return [
                'success' => false,
                'message' => t('screens.admin.users_manager.device_pin_length_error'),
            ];
        }

        $device = $this->getDevice($id);
        if (!$device) {
            return [
                'success' => false,
                'message' => 'Dispositivo no encontrado.',
            ];
        }

        $manager = $this->pairingManager ?? app(DevicePairingManager::class);
        $success = $manager->approve($cleanPin, $device);

        if ($success) {
            $device->pairing_pin = $cleanPin;
            if (empty($device->device_token)) {
                $device->device_token = Str::random(60);
            }
            $device->save();

            return [
                'success' => true,
                'message' => t('screens.admin.users_manager.device_paired_success'),
            ];
        }

        return [
            'success' => false,
            'message' => t('screens.admin.users_manager.device_invalid_pin'),
        ];
    }

    /**
     * Just-In-Time pairing flow: registers and pairs a new device simultaneously.
     *
     * @param array{
     *     name: string,
     *     roles?: list<string>|string,
     *     unit_id?: int|string|null,
     *     unit_ids?: list<int|string>,
     *     specs?: array<string, mixed>|null
     * } $data
     * @return array{success: bool, message: string, device?: Device}
     */
    public function pairAndCreateDevice(string $pin, array $data): array
    {
        $cleanPin = trim($pin);
        if (strlen($cleanPin) !== 4) {
            return [
                'success' => false,
                'message' => t('screens.admin.users_manager.device_pin_length_error'),
            ];
        }

        $manager = $this->pairingManager ?? app(DevicePairingManager::class);
        if (!$manager->isValidPin($cleanPin)) {
            return [
                'success' => false,
                'message' => t('screens.admin.users_manager.device_invalid_pin'),
            ];
        }

        DB::beginTransaction();
        try {
            $device = $this->createDevice($data);
            $success = $manager->approve($cleanPin, $device);

            if (!$success) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => t('screens.admin.users_manager.device_invalid_pin'),
                ];
            }

            $device->pairing_pin = $cleanPin;
            if (empty($device->device_token)) {
                $device->device_token = Str::random(60);
            }
            $device->save();

            DB::commit();

            return [
                'success' => true,
                'message' => t('screens.admin.users_manager.device_paired_success'),
                'device' => $device,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getDevice(int|string $id): ?Device
    {
        return Device::with(['tokens', 'roles', 'globalRoles', 'usimUnits'])->find($id);
    }

    /**
     * @return Collection<int, Device>
     */
    public function getAllDevices(): Collection
    {
        return Device::with(['tokens', 'roles', 'globalRoles', 'usimUnits'])->orderBy('name')->get();
    }
}
