<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Device;

use App\Models\Device;
use App\Services\Device\DeviceService;
use App\Services\Role\RoleService;
use App\Services\Units\UsimUnitsService;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Modals\ConfirmDialog;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Screen for creating, editing, pairing, and deleting devices.
 */
class EditDevice extends Screen
{
    public const DEFAULT_SUBMIT_ACTION = 'submit_save_device';
    public const DEFAULT_CANCEL_ACTION = 'close_edit_device';
    public const DELETE_ACTION = 'delete_device';
    public const PAIR_UNPAIR_ACTION = 'pair_unpair_device';
    private const DEVICES_I18N_PREFIX = 'screens.admin.users_manager.';
    private const CONTAINER_PADDING = 5;
    private const BUTTONS_GAP = 5;

    public static Visibility $visibility = Visibility::AUTHENTICATED;

    protected Label $lbl_edit_device_result;
    protected ?Label $device_status_banner = null;
    protected ?Button $btn_pair_unpair_device = null;
    protected ?Button $btn_delete_device = null;

    protected DeviceService $deviceService;
    protected RoleService $roleService;
    protected UsimUnitsService $unitsService;

    public function __construct(
        ?DeviceService $deviceService = null,
        ?RoleService $roleService = null,
        ?UsimUnitsService $unitsService = null,
    ) {
        $this->deviceService = $deviceService ?? app(DeviceService::class);
        $this->roleService = $roleService ?? app(RoleService::class);
        $this->unitsService = $unitsService ?? app(UsimUnitsService::class);
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $rawDevice = $params['device'] ?? null;
        $rawDeviceId = $params['device_id'] ?? $params['id'] ?? request()->query('id') ?? request()->query('device_id');

        $device = $this->resolveDevice($rawDevice, $rawDeviceId);
        $isEditing = $device !== null;

        $wrapper = UI::container('edit_device_wrapper')
            ->maxWidth(Size::px(600))
            ->width(Size::full())
            ->centerHorizontal();

        $card = UI::container('edit_device_dialog')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->padding(Spacing::px(self::CONTAINER_PADDING))
            ->gap(Spacing::px(self::BUTTONS_GAP));

        $header = UI::container('edit_device_modal_header')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->plain()
            ->padding(Spacing::zero());

        $header->add(
            UI::label('dialog_title')
                ->text(t(self::DEVICES_I18N_PREFIX . ($isEditing ? 'edit_device_title' : 'create_device_title')))
                ->style('h2')
        );

        $header->add(
            UI::button('btn_close_modal')
                ->label('✕')
                ->action(self::DEFAULT_CANCEL_ACTION)
                ->style('secondary')
                ->variant('ghost')
                ->plain()
        );

        $card->add($header);

        // Hidden input for device_id
        $card->add(
            UI::input('device_id')
                ->type('hidden')
                ->value($isEditing ? (string) $device->id : '')
        );

        // Name input
        $card->add(
            UI::input('device_name')
                ->label(t(self::DEVICES_I18N_PREFIX . 'device_name_label'))
                ->placeholder('Ej: Smart TV Sala Principal')
                ->value($isEditing ? $device->name : '')
                ->required(true)
                ->autocomplete('off')
                ->width(Size::full())
        );

        // Status banner if editing
        if ($isEditing) {
            $isPaired = $device->tokens->isNotEmpty() || !empty($device->device_token);
            $statusText = $isPaired
                ? '✅ ' . t(self::DEVICES_I18N_PREFIX . 'devices_status_paired')
                : '⚠️ ' . t(self::DEVICES_I18N_PREFIX . 'devices_status_unpaired');

            $card->add(
                UI::label('device_status_banner')
                    ->text($statusText)
                    ->style($isPaired ? 'success' : 'warning')
            );
        }

        // Unit checkboxes
        $this->buildUnitCheckboxes($card, $device);

        // Role checkboxes
        $this->buildRoleCheckboxes($card, $device);

        // Status/Result label
        $this->lbl_edit_device_result = UI::label('lbl_edit_device_result')->text('');
        $card->add($this->lbl_edit_device_result);

        // Buttons
        $this->buildActionButtons($card, $device);

        $wrapper->add($card);
        $container->add($wrapper);
    }

    protected function postLoadUI(): void
    {
        if (isset($this->lbl_edit_device_result)) {
            $this->lbl_edit_device_result->text('')->style('');
        }
    }

    protected function buildUnitCheckboxes(Container $card, ?Device $device): void
    {
        $units = $this->unitsService->getAvailableUnits();
        $mainUnit = UsimUnit::where('slug', 'main')->first();
        if ($mainUnit && !$units->contains('id', $mainUnit->id)) {
            $units = $units->prepend($mainUnit);
        }

        $unitOptions = [];
        foreach ($units as $u) {
            $uName = ($u->display_name !== $u->translation_key) ? $u->display_name : ucfirst($u->slug);
            if ($u->slug === 'main') {
                $label = t('screens.admin.users_manager.device_unit_institutional', [], "Institucional / Todos ({$uName})");
            } else {
                $label = $uName;
            }
            $unitOptions[] = [
                'value' => (string) $u->id,
                'label' => $label,
            ];
        }

        $activeUnitId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
        /** @var list<string> $selectedUnitIds */
        $selectedUnitIds = [];
        if ($device !== null) {
            $selectedUnitIds = array_values(
                $device->usimUnits->pluck('id')->map(static function (mixed $id): string {
                    return is_int($id) || is_string($id) ? (string) $id : '';
                })->filter(static fn(string $id): bool => $id !== '')->all()
            );
        } elseif ($activeUnitId) {
            $selectedUnitIds = [(string) $activeUnitId];
        } elseif ($mainUnit) {
            $selectedUnitIds = [(string) $mainUnit->id];
        }

        $card->add(
            UI::checkbox('device_units')
                ->label(t(self::DEVICES_I18N_PREFIX . 'device_units_label'))
                ->options($unitOptions)
                ->selectedValues($selectedUnitIds)
                ->vertical()
        );
    }

    protected function buildRoleCheckboxes(Container $card, ?Device $device): void
    {
        $roles = $this->roleService->getRolesForActor(Device::class);

        /** @var list<array{value: string, label: string}> $roleOptions */
        $roleOptions = array_map(static fn(UsimRole $role): array => [
            'value' => $role->name,
            'label' => t("role.{$role->name}.name"),
        ], $roles);

        /** @var list<string> $selectedRoles */
        $selectedRoles = [];
        if ($device !== null) {
            $rolesCollection = $device->roles->isNotEmpty()
                ? $device->roles
                : ($device->relationLoaded('globalRoles') && $device->globalRoles->isNotEmpty()
                    ? $device->globalRoles
                    : $device->roles);

            $selectedRoles = array_values(array_filter(
                $rolesCollection->pluck('name')->all(),
                static fn(mixed $name): bool => is_string($name) && $name !== ''
            ));
        } elseif (!empty($roles)) {
            $selectedRoles = [$roles[0]->name];
        }

        $card->add(
            UI::checkbox('device_roles')
                ->label(t(self::DEVICES_I18N_PREFIX . 'device_roles_label'))
                ->options($roleOptions)
                ->selectedValues($selectedRoles)
                ->required(true)
                ->vertical()
        );
    }

    protected function buildActionButtons(Container $card, ?Device $device): void
    {
        $isEditing = $device !== null;

        $buttonsContainer = UI::container('device_dialog_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->plain()
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->gap(Spacing::px(self::BUTTONS_GAP))
            ->width(Size::full());

        $leftButtons = UI::container('device_left_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->rounded(false)
            ->gap(Spacing::px(self::BUTTONS_GAP));

        if ($isEditing) {
            $isPaired = $device->tokens->isNotEmpty() || !empty($device->device_token);
            $label = $isPaired ?
                t(self::DEVICES_I18N_PREFIX . 'device_unpair_button') :
                t(self::DEVICES_I18N_PREFIX . 'pair_device');
            $style = $isPaired ? "success" : "warning";

            $leftButtons->add(
                UI::button('btn_pair_unpair_device')
                    ->label($label)
                    ->style($style)
                    ->action(self::PAIR_UNPAIR_ACTION, [
                        'is_paired' => $isPaired,
                        'device_id' => $device->id,
                    ])
            );

            $leftButtons->add(
                UI::button('btn_delete_device')
                    ->label(t(self::DEVICES_I18N_PREFIX . 'device_delete_button'))
                    ->style('danger')
                    ->enabled(!$isPaired)
                    ->action(self::DELETE_ACTION, [
                        'device_id' => $device->id,
                    ])
            );
        }

        $rightButtons = UI::container('device_right_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->rounded(false)
            ->gap(Spacing::px(self::BUTTONS_GAP));

        $rightButtons->add(
            UI::button('btn_cancel_device')
                ->label(t('modal.cancel'))
                ->style('secondary')
                ->action(self::DEFAULT_CANCEL_ACTION)
        );

        $rightButtons->add(
            UI::button('btn_save_device')
                ->label(t(self::DEVICES_I18N_PREFIX . ($isEditing ? 'edit_button' : 'add_device')))
                ->style('primary')
                ->action(self::DEFAULT_SUBMIT_ACTION)
        );

        $buttonsContainer
            ->add($leftButtons)
            ->add($rightButtons);

        $card->add($buttonsContainer);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSubmitSaveDevice(array $params): void
    {
        $rawId = $params['device_id'] ?? $params['id'] ?? null;
        $deviceId = is_numeric($rawId) ? (int) $rawId : null;

        $name = trim(is_string($params['device_name'] ?? null)
            ? $params['device_name']
            : (is_string($params['name'] ?? null) ? $params['name'] : ''));

        if ($name === '') {
            $message = t('validation.required', ['attribute' => t(self::DEVICES_I18N_PREFIX . 'device_name_label')]);
            $this->toast($message, 'error');
            if (isset($this->lbl_edit_device_result)) {
                $this->lbl_edit_device_result->text($message)->style('error');
            }
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
            $roles = array_values(array_filter($rawRoles, static fn(mixed $r): bool => is_string($r) && $r !== ''));
        } else {
            $roles = [];
        }

        if (empty($roles)) {
            $message = t('validation.required', ['attribute' => t(self::DEVICES_I18N_PREFIX . 'device_roles_label')]);
            $this->toast($message, 'error');
            if (isset($this->lbl_edit_device_result)) {
                $this->lbl_edit_device_result->text($message)->style('error');
            }
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
            $message = t(self::DEVICES_I18N_PREFIX . 'device_updated');
            event(new UsimEvent('device_updated', ['device_id' => $deviceId, 'data' => $devicePayload, 'message' => $message]));
        } else {
            $createdDevice = $this->deviceService->createDevice($devicePayload);
            $message = t(self::DEVICES_I18N_PREFIX . 'device_created');
            event(new UsimEvent('device_created', ['device_id' => $createdDevice->id, 'data' => $devicePayload, 'message' => $message]));
        }

        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onDeleteDevice(array $params): void
    {
        $rawId = $params['device_id'] ?? null;
        $deviceId = is_numeric($rawId) ? (int) $rawId : null;

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
        $rawId = $params['device_id'] ?? null;
        $deviceId = is_numeric($rawId) ? (int) $rawId : null;

        if ($deviceId === null) {
            $this->toast(t('Device ID is required for deletion'), 'error');
            return;
        }

        $deleted = $this->deviceService->deleteDevice($deviceId);
        if ($deleted) {
            $message = t(self::DEVICES_I18N_PREFIX . 'device_deleted');
            event(new UsimEvent('device_deleted', ['device_id' => $deviceId, 'message' => $message]));
        }

        if ($this->isOpenedAsModal()) {
            $this->closeModal();
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onPairUnpairDevice(array $params): void
    {
        $isPaired = $params['is_paired'] ?? false;

        if ($isPaired) {
            $this->onUnpairDevice($params);
        } else {
            $this->onPairDeviceClicked($params);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onUnpairDevice(array $params): void
    {
        $rawId = $params['device_id'] ?? null;
        $deviceId = is_numeric($rawId) ? (int) $rawId : null;

        if ($deviceId === null) {
            $this->toast(t('Device ID is required'), 'error');
            return;
        }

        $this->deviceService->unpairDevice($deviceId);

        event(new UsimEvent('device_unpaired', ['device_id' => $deviceId]));

        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onPairDeviceClicked(array $params): void
    {
        $rawId = $params['device_id'] ?? null;
        $deviceId = is_numeric($rawId) ? (int) $rawId : null;
        $device = $deviceId !== null ? $this->deviceService->getDevice($deviceId) : null;

        $this->openModal(Link::class, [
            'device' => $device,
            'device_id' => $deviceId,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onCloseEditDevice(array $params = []): void
    {
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onCloseModal(array $params = []): void
    {
        $this->onCloseEditDevice($params);
    }

    protected function resolveDevice(mixed $rawDevice, mixed $rawDeviceId): ?Device
    {
        if ($rawDevice instanceof Device) {
            return $rawDevice;
        }

        $deviceId = null;
        if (is_int($rawDeviceId) || (is_string($rawDeviceId) && is_numeric($rawDeviceId))) {
            $deviceId = (int) $rawDeviceId;
        } elseif (is_array($rawDevice) && isset($rawDevice['id']) && is_numeric($rawDevice['id'])) {
            $deviceId = (int) $rawDevice['id'];
        }

        if ($deviceId !== null && $deviceId > 0) {
            return $this->deviceService->getDevice($deviceId);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onDevicePaired(array $params): void
    {
        $message = $params['message'] ?? '';
        $device = $this->resolveDevice($params['device'] ?? null, $params['device_id'] ?? null);
        $isPaired = $device !== null && $device->isPaired();
        $statusText = $isPaired
            ? '✅ ' . t(self::DEVICES_I18N_PREFIX . 'devices_status_paired')
            : '⚠️ ' . t(self::DEVICES_I18N_PREFIX . 'devices_status_unpaired');


        $label = $isPaired ?
            t(self::DEVICES_I18N_PREFIX . 'device_unpair_button') :
            t(self::DEVICES_I18N_PREFIX . 'pair_device');
        $style = $isPaired ? "success" : "warning";

        $this->device_status_banner
            ?->text($statusText)
            ?->style($style);

        $this->btn_pair_unpair_device
            ?->label($label)
            ?->style($style);

        $this->btn_delete_device
            ?->enabled(!$isPaired);

        if (\is_string($message)) {
            $this->toast($message, $style);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onDeviceUnpaired(array $params): void
    {
        $message = $params['message'] ?? '';
        $device = $this->resolveDevice($params['device'] ?? null, $params['device_id'] ?? null);
        $isPaired = $device !== null && $device->isPaired();
        $statusText = $isPaired
            ? '✅ ' . t(self::DEVICES_I18N_PREFIX . 'devices_status_paired')
            : '⚠️ ' . t(self::DEVICES_I18N_PREFIX . 'devices_status_unpaired');

        $label = $isPaired ?
            t(self::DEVICES_I18N_PREFIX . 'device_unpair_button') :
            t(self::DEVICES_I18N_PREFIX . 'pair_device');
        $style = $isPaired ? "success" : "warning";

        $this->device_status_banner
            ?->text($statusText)
            ?->style($style);

        $this->btn_pair_unpair_device
            ?->label($label)
            ?->style($style);

        $this->btn_delete_device
            ?->enabled(!$isPaired);

        if (\is_string($message)) {
            $this->toast($message, $style);
        }
    }
}
