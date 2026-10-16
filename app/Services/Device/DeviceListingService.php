<?php
// @usim: feature="admin", type="service"
namespace App\Services\Device;

use App\Models\Device;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Support\EloquentListingService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Listing service for devices with multi-unit isolation support.
 *
 * @extends EloquentListingService<Device>
 */
class DeviceListingService extends EloquentListingService
{
    protected string $modelClass = Device::class;

    protected array $with = ['tokens', 'roles', 'globalRoles', 'usimUnits'];

    /**
     * Explicit unit context override. If null, automatically resolves from session/teams.
     */
    protected ?int $unitContextId = null;

    public function setUnitContext(?int $unitId): self
    {
        $this->unitContextId = $unitId;
        return $this;
    }

    public function getUnitContext(): ?int
    {
        return $this->unitContextId;
    }

    /**
     * Resolves the active organizational unit ID for filtering devices.
     */
    protected function resolveActiveUnitId(): ?int
    {
        if ($this->unitContextId !== null) {
            return $this->unitContextId;
        }

        $cachedUnit = \Idei\Usim\Support\UIStateManager::getActiveUnit();
        if ($cachedUnit !== null) {
            $rawId = UsimUnit::where('slug', $cachedUnit)->value('id');
            if (is_numeric($rawId)) {
                return (int) $rawId;
            }
        }

        if (function_exists('getPermissionsTeamId') && getPermissionsTeamId()) {
            return (int) getPermissionsTeamId();
        }

        $rawMainId = UsimUnit::where('slug', 'main')->value('id');
        return is_numeric($rawMainId) ? (int) $rawMainId : null;
    }

    /**
     * @return Builder<Device>
     */
    protected function newBaseQuery(): Builder
    {
        $query = parent::newBaseQuery();

        if (config('permission.teams', false)) {
            $activeUnitId = $this->resolveActiveUnitId();
            $rawMainId = UsimUnit::where('slug', 'main')->value('id');
            $mainUnitId = is_numeric($rawMainId) ? (int) $rawMainId : null;

            if ($activeUnitId !== null || $mainUnitId !== null) {
                $query->where(function (Builder $q) use ($activeUnitId, $mainUnitId): void {
                    if ($activeUnitId !== null) {
                        $q->where(function (Builder $aq) use ($activeUnitId): void {
                            $aq->whereHas('usimUnits', static function (Builder $uq) use ($activeUnitId): void {
                                $uq->where('usim_units.id', $activeUnitId);
                            })
                            ->orWhereHas('roles', static function (Builder $rq) use ($activeUnitId): void {
                                $rq->wherePivot('usim_unit_id', $activeUnitId);
                            });
                        });

                        if ($mainUnitId !== null && $mainUnitId !== $activeUnitId) {
                            $q->orWhere(function (Builder $mq) use ($mainUnitId): void {
                                $mq->whereHas('usimUnits', static function (Builder $uq) use ($mainUnitId): void {
                                    $uq->where('usim_units.id', $mainUnitId);
                                })
                                ->orWhereHas('roles', static function (Builder $rq) use ($mainUnitId): void {
                                    $rq->wherePivot('usim_unit_id', $mainUnitId);
                                });
                            });
                        }
                    } elseif ($mainUnitId !== null) {
                        $q->where(function (Builder $mq) use ($mainUnitId): void {
                            $mq->whereHas('usimUnits', static function (Builder $uq) use ($mainUnitId): void {
                                $uq->where('usim_units.id', $mainUnitId);
                            })
                            ->orWhereHas('roles', static function (Builder $rq) use ($mainUnitId): void {
                                $rq->wherePivot('usim_unit_id', $mainUnitId);
                            });
                        });
                    }

                    // Devices without any unit assignment are considered institutional/public (main)
                    $q->orWhereDoesntHave('usimUnits');
                });
            }
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    protected function searchableFields(): array
    {
        $roleField = config('permission.teams') ? 'globalRoles.name' : 'roles.name';

        return [
            'name' => 'name',
            'pairing_pin' => 'pairing_pin',
            'roles' => $roleField,
        ];
    }

    /**
     * @return array<string, array{path: string, operator?: string, cast?: 'int'|'float'|'bool'|'string'}>
     */
    protected function filterableFields(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    protected function sortableFields(): array
    {
        return [
            'name' => 'name',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }

    /**
     * @return array{field: string, direction: 'asc'|'desc'}
     */
    protected function defaultSort(): array
    {
        return [
            'field' => 'name',
            'direction' => 'asc',
        ];
    }
}
