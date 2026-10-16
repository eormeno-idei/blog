<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Admin\TableModels;

use App\Models\Device;
use App\Services\Device\DeviceListingService;
use App\Services\Units\UsimUnitsService;
use Idei\Usim\Components\Table;
use Idei\Usim\DataTable\AbstractListingTableModel;

/**
 * Table model for managing devices listing.
 *
 * @extends AbstractListingTableModel<Device>
 */
class DeviceTableModel extends AbstractListingTableModel
{
    protected UsimUnitsService $unitsService;

    public function __construct(Table $tableBuilder, ?UsimUnitsService $unitsService = null)
    {
        $this->unitsService = $unitsService ?? app(UsimUnitsService::class);
        parent::__construct($tableBuilder);
    }

    protected function resolveListingService(): DeviceListingService
    {
        return app(DeviceListingService::class);
    }

    public function getColumns(): array
    {
        $prefix = 'screens.admin.users_manager';
        return [
            'name' => [
                'label' => t("{$prefix}.devices_column_name"),
                'width' => 220,
                'sort_by' => 'name',
            ],
            'status' => [
                'label' => t("{$prefix}.devices_column_status"),
                'width' => 140,
            ],
            'units' => [
                'label' => t("{$prefix}.devices_column_units"),
                'width' => 160,
            ],
            'roles' => [
                'label' => t("{$prefix}.devices_column_roles"),
                'width' => 180,
            ],
            'updated_at' => [
                'label' => t("{$prefix}.devices_column_updated_at"),
                'width' => 140,
                'sort_by' => 'updated_at',
            ],
        ];
    }

    /**
     * @param Device $item
     * @return array{
     *     _model_id: int|string,
     *     name: string,
     *     status: string,
     *     units: string,
     *     roles: string,
     *     updated_at: string
     * }
     */
    protected function formatRow(object $item): array
    {
        /** @var Device $device */
        $device = $item;
        $isPaired = $device->tokens->isNotEmpty() || !empty($device->device_token);

        $statusText = $isPaired
            ? t('screens.admin.users_manager.devices_status_paired')
            : t('screens.admin.users_manager.devices_status_unpaired');

        $status = $isPaired ? "✅ {$statusText}" : "⚠️ {$statusText}";

        return [
            '_model_id' => $device->id,
            'name' => $device->name,
            'status' => $status,
            'units' => $this->formatUnits($device),
            'roles' => $this->formatRoles($device),
            'updated_at' => $device->updated_at?->format('Y-m-d H:i') ?? '-',
        ];
    }

    private function formatUnits(Device $device): string
    {
        $units = $device->relationLoaded('usimUnits') ? $device->usimUnits : $device->usimUnits()->get();

        $operationalUnits = $units->filter(static function ($unit): bool {
            return $unit->type !== 'system' && !in_array($unit->slug, ['main', 'lobby'], true);
        })->values();

        $isPublic = $units->isEmpty() || $units->contains('slug', 'main');

        $main = $units->firstWhere('slug', 'main');
        $mainLabel = ($main && $main->display_name !== $main->translation_key)
            ? $main->display_name
            : 'Institucional';

        if ($isPublic && $operationalUnits->isEmpty()) {
            return '🏛️ ' . t('screens.admin.users_manager.device_unit_institutional', [], "{$mainLabel} (Todos)");
        }

        if ($operationalUnits->isNotEmpty()) {
            $first = $operationalUnits->first();
            $firstName = ($first->display_name !== $first->translation_key)
                ? $first->display_name
                : ucfirst($first->slug);

            $extraCount = $operationalUnits->count() - 1;
            $suffix = $extraCount > 0 ? " (+{$extraCount})" : '';

            if ($isPublic) {
                return "🏛️ {$firstName}{$suffix}";
            }

            return "{$firstName}{$suffix}";
        }

        if ($main) {
            return ($main->display_name !== $main->translation_key)
                ? $main->display_name
                : ucfirst($main->slug);
        }

        return '-';
    }

    private function formatRoles(Device $device): string
    {
        $rolesCollection = $device->roles->isNotEmpty()
            ? $device->roles
            : ($device->relationLoaded('globalRoles') && $device->globalRoles->isNotEmpty()
                ? $device->globalRoles
                : $device->roles);

        $roleNames = $rolesCollection
            ->pluck('name')
            ->filter(static fn ($name): bool => is_string($name))
            ->unique()
            ->values()
            ->toArray();

        /** @var list<string> $roles */
        $roles = array_map(static fn (mixed $name): string => is_scalar($name) || $name instanceof \Stringable ? t("role.{$name}.name") : '', $roleNames);

        return $roles ? implode(', ', array_filter($roles)) : '-';
    }
}
