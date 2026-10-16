<?php
// @usim: feature="admin", type="service"
namespace App\Services\Role;

use Idei\Usim\Models\UsimRole;
use Idei\Usim\Support\EloquentListingService;

/**
 * Read-only listing service for roles.
 *
 * @extends EloquentListingService<UsimRole>
 */
class RoleListingService extends EloquentListingService
{
    protected string $modelClass = UsimRole::class;

    protected array $with = ['usimSetting'];

    /**
     * @return array<string, string>
     */
    protected function searchableFields(): array
    {
        return [
            'name' => 'name',
            'guard_name' => 'guard_name',
            'home_screen' => 'usimSetting.home_screen',
            'priority' => 'usimSetting.priority',
        ];
    }

    /**
     * @return array<string, array{path: string, operator?: string, cast?: 'int'|'float'|'bool'|'string'}>
     */
    protected function filterableFields(): array
    {
        return [
            'guard_name' => ['path' => 'guard_name'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function sortableFields(): array
    {
        return [
            'name' => 'name',
            'guard_name' => 'guard_name',
            'home_screen' => 'usimSetting.home_screen',
            'priority' => 'usimSetting.priority',
        ];
    }

    /**
     * @return array{field: string, direction: 'asc'|'desc'}
     */
    protected function defaultSort(): array
    {
        return [
            'field' => 'name',
            'direction' => 'desc',
        ];
    }

}
