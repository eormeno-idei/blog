<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Admin\Concerns;

use App\Services\Device\DeviceListingService;
use App\Services\Device\DeviceService;
use App\UI\Screens\Admin\TableModels\DeviceTableModel;
use App\UI\Screens\Device\EditDevice;
use App\UI\Screens\Device\Link;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Table;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\Modals\ConfirmDialog;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Trait to manage the devices CRUD UI section, table events, and PIN pairing workflows.
 *
 * @mixin Screen
 * @property DeviceService $deviceService
 */
trait ManagesDevicesSection
{
    protected const string DEVICES_I18N_PREFIX = 'screens.admin.users_manager.';

    protected Table $devices_table;
    protected Input $search_devices;
    protected Button $add_device_btn;
    protected Button $pair_device_btn;

    protected function buildDevicesCrudContainer(): Container
    {
        $devicesCrudContainer = UI::container('devices_crud_container')
            ->layout(LayoutType::VERTICAL)
            ->fullWidth()
            ->gap(Spacing::px(4))
            ->minHeight(Size::px(550));

        $toolbar = UI::container('devices_toolbar')
            ->layout(LayoutType::HORIZONTAL)
            ->fullWidth()
            ->padding(Spacing::px(10))
            ->gap(Spacing::px(10));

        $search = UI::input('search_devices')
            ->placeholder(t(self::DEVICES_I18N_PREFIX . 'devices_search_placeholder'))
            ->width(Size::px(300))
            ->autocomplete('off')
            ->onInput('search_devices', [])
            ->debounce(500);

        $addBtn = UI::button('add_device_btn')
            ->label(t(self::DEVICES_I18N_PREFIX . 'add_device'))
            ->style('secondary')
            ->action('add_device_clicked')
            ->icon('plus');

        $pairBtn = UI::button('pair_device_btn')
            ->label(t(self::DEVICES_I18N_PREFIX . 'pair_device'))
            ->style('secondary')
            ->action('pair_device_clicked')
            ->icon('link');

        $toolbar
            ->add($search)
            ->add($addBtn)
            ->add($pairBtn);

        $devicesTable = UI::table('devices_table')
            ->dataModel(DeviceTableModel::class)
            ->selectionMode(SelectionMode::SINGLE)
            ->sortedBy('name')
            ->fitContainer(
                availableHeight: 550,
                rowHeight: 45,
                hasToolbar: true,
                paginated: true
            );

        $devicesCrudContainer
            ->add($toolbar)
            ->add($devicesTable);

        return $devicesCrudContainer;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onDevicesTableColumnClicked(array $params): void
    {
        $this->handleTableSort($this->devices_table, $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSearchDevices(array $params): void
    {
        $search = trim($this->searchParam($params, ['value', 'search_devices']));
        $this->devices_table->setSearchTerm($search);
        $this->search_devices->value($search);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onAddDeviceClicked(array $params): void
    {
        Screen::openAsModal(
            caller: $this->screen,
            screenClass: EditDevice::class
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onPairDeviceClicked(array $params): void
    {
        $deviceId = $this->optionalIntParam($params, 'device_id');
        if ($deviceId === null) {
            $selected = $this->devices_table->select();
            if (is_int($selected) || (is_string($selected) && is_numeric($selected))) {
                $deviceId = (int) $selected;
            }
        }

        $device = $deviceId !== null ? $this->deviceService->getDevice($deviceId) : null;
        $devicesOptions = [];
        if (!$device) {
            $devices = app(DeviceListingService::class)->all();
            foreach ($devices as $d) {
                $devicesOptions[] = [
                    'value' => $d->id,
                    'label' => $d->name,
                ];
            }
        }

        $caller = $this->screen ?? ($this instanceof \Idei\Usim\Screen ? $this : null);
        $caller?->openModal(Link::class, [
            'device' => $device,
            'devicesOptions' => $devicesOptions,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onDevicesTableRowClicked(array $params): void
    {
        $deviceId = $this->selectableId($params, 'model_id');
        if ($deviceId === null) {
            $this->toast('Dispositivo no encontrado', 'error');
            return;
        }

        $device = $this->deviceService->getDevice($deviceId);
        if (!$device) {
            $this->toast('Dispositivo no encontrado', 'error');
            return;
        }

        $this->devices_table->select($deviceId);

        Screen::openAsModal(
            params: [
                'device' => $device,
            ],
            caller: $this->screen,
            screenClass: EditDevice::class
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSubmitSaveDevice(array $params): void
    {
        $deviceId = $this->optionalIntParam($params, 'device_id') ?? $this->optionalIntParam($params, 'id');
        $name = trim($this->stringParamOrDefault($params, 'device_name', $this->stringParamOrDefault($params, 'name', '')));

        if ($name === '') {
            $this->toast(t('validation.required', ['attribute' => t(self::DEVICES_I18N_PREFIX . 'device_name_label')]), 'error');
            return;
        }

        $rawUnits = $params['device_units'] ?? $params['unit_ids'] ?? null;
        $rawUnitId = $params['device_unit_id'] ?? $params['unit_id'] ?? null;

        /** @var list<int>|null $unitIds */
        $unitIds = null;
        if (is_array($rawUnits)) {
            $unitIds = array_values(array_filter(
                array_map(static fn(mixed $u): ?int => is_numeric($u) ? (int) $u : null, $rawUnits),
                static fn(?int $id): bool => $id !== null && $id > 0
            ));
        } elseif (is_int($rawUnitId) || (is_string($rawUnitId) && $rawUnitId !== '' && $rawUnitId !== '0')) {
            $unitIds = is_numeric($rawUnitId) ? [(int) $rawUnitId] : null;
        }

        $rawRoles = $params['device_roles'] ?? $params['roles'] ?? [];
        if (is_string($rawRoles)) {
            $roles = [$rawRoles];
        } elseif (is_array($rawRoles)) {
            $roles = array_values(array_filter($rawRoles, static fn($r): bool => is_string($r) && $r !== ''));
        } else {
            $roles = [];
        }

        if (empty($roles)) {
            $this->toast(t('validation.required', ['attribute' => t(self::DEVICES_I18N_PREFIX . 'device_roles_label')]), 'error');
            return;
        }

        /** @var array{name: string, roles: list<string>, unit_ids?: list<int>} $devicePayload */
        $devicePayload = [
            'name' => $name,
            'roles' => $roles,
        ];
        if ($unitIds !== null) {
            $devicePayload['unit_ids'] = $unitIds;
        }

        if ($deviceId) {
            $this->deviceService->updateDevice($deviceId, $devicePayload);
            $this->toast(t(self::DEVICES_I18N_PREFIX . 'device_updated'), 'success');
        } else {
            $this->deviceService->createDevice($devicePayload);
            $this->toast(t(self::DEVICES_I18N_PREFIX . 'device_created'), 'success');
        }

        $this->devices_table->refresh();
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSubmitApproveDevicePairing(array $params): void
    {
        $pairingIdRaw = $params['pairing_device_id'] ?? $params['device_id'] ?? null;
        $pin = trim($this->stringParamOrDefault($params, 'input_pin', $this->stringParamOrDefault($params, 'pin', '')));

        if (strlen($pin) !== 4) {
            $this->toast(t(self::DEVICES_I18N_PREFIX . 'device_pin_length_error'), 'error');
            return;
        }

        $deviceId = is_numeric($pairingIdRaw) ? (int) $pairingIdRaw : null;
        if ($deviceId === null || $deviceId <= 0) {
            $this->toast(t(self::DEVICES_I18N_PREFIX . 'device_select_label'), 'error');
            return;
        }

        $result = $this->deviceService->pairDevice($deviceId, $pin);
        if ($result['success']) {
            $this->toast($result['message'], 'success');
            $this->devices_table->refresh();
            $this->closeModal();
        } else {
            $this->toast($result['message'], 'error');
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onUnpairDevice(array $params): void
    {
        $deviceId = $this->optionalIntParam($params, 'device_id');
        if ($deviceId === null) {
            $this->toast(t('Device ID is required'), 'error');
            return;
        }

        $this->deviceService->unpairDevice($deviceId);
        $this->toast(t(self::DEVICES_I18N_PREFIX . 'device_unpaired'), 'success');
        $this->devices_table->refresh();
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onDeleteDevice(array $params): void
    {
        $deviceId = $this->optionalIntParam($params, 'device_id');
        if ($deviceId === null) {
            $this->toast(t('Device ID is required'), 'error');
            return;
        }

        $device = $this->deviceService->getDevice($deviceId);
        if (!$device) {
            $this->toast(t('Device not found'), 'error');
            return;
        }

        ConfirmDialog::open(
            caller: $this,
            type: DialogType::WARNING,
            title: t(self::DEVICES_I18N_PREFIX . 'device_delete_confirm_title'),
            message: t(self::DEVICES_I18N_PREFIX . 'device_delete_confirm_msg', ['name' => $device->name]),
            confirmAction: 'confirm_delete_device',
            confirmParams: ['device_id' => $deviceId],
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onConfirmDeleteDevice(array $params): void
    {
        $deviceId = $this->optionalIntParam($params, 'device_id');
        if ($deviceId === null) {
            $this->toast(t('Device ID is required for deletion'), 'error');
            return;
        }

        $deleted = $this->deviceService->deleteDevice($deviceId);
        if ($deleted) {
            $this->toast(t(self::DEVICES_I18N_PREFIX . 'device_deleted'), 'success');
        }

        $this->devices_table->refresh();
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onUnitChanged(array $params): void
    {
        $this->devices_table->refresh();
    }

    /**
     * Handles the 'device_created' event, fired by: EditDevice::class.
     *
     * @param array<string, mixed> $params
     */
    public function onDeviceCreated(array $params): void
    {
        $rawMessage = $params['message'] ?? '';
        $message = is_string($rawMessage) ? $rawMessage : '';
        $this->toast($message, 'success');
        $this->devices_table->refresh();
    }

    /**
     * Handles the 'device_updated' event, fired by: EditDevice::class.
     *
     * @param array<string, mixed> $params
     */
    public function onDeviceUpdated(array $params): void
    {
        $rawMessage = $params['message'] ?? '';
        $message = is_string($rawMessage) ? $rawMessage : '';
        $this->toast($message, 'success');
        $this->devices_table->refresh();
    }

    /**
     * Handles the 'device_deleted' event, fired by: EditDevice::class.
     *
     * @param array<string, mixed> $params
     */
    public function onDeviceDeleted(array $params): void
    {
        $rawMessage = $params['message'] ?? '';
        $message = is_string($rawMessage) ? $rawMessage : '';
        $this->toast($message, 'success');
        $this->devices_table->refresh();
    }

    /**
     * Handles the 'device_paired' event, fired by: Link::class.
     *
     * @param array<string, mixed> $params
     */
    public function onDevicePaired(array $params): void
    {
        $this->devices_table->refresh();
    }

    /**
     * Handles the 'device_unpaired' event, fired by: EditDevice::class.
     *
     * @param array<string, mixed> $params
     */
    public function onDeviceUnpaired(array $params): void
    {
        $this->devices_table->refresh();
    }
}
