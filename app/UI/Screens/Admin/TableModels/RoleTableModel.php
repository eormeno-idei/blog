<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Admin\TableModels;

use App\Services\Role\RoleListingService;
use Idei\Usim\DataTable\AbstractListingTableModel;
use Idei\Usim\Models\UsimRole;

/**
 * Table model for managing roles listing.
 *
 * @extends AbstractListingTableModel<UsimRole>
 */
class RoleTableModel extends AbstractListingTableModel
{
    protected function resolveListingService(): RoleListingService
    {
        return app(RoleListingService::class);
    }

    public function getColumns(): array
    {
        $prefix = 'screens.admin.users_manager';
        return [
            'name' => ['label' => t("{$prefix}.roles_column_name"), 'sort_by' => 'name'],
            'guard_name' => ['label' => t("{$prefix}.roles_column_guard"), 'sort_by' => 'guard_name', 'width' => 100],
            'home_screen' => ['label' => t("{$prefix}.roles_column_home_screen"), 'sort_by' => 'home_screen'],
            'priority' => ['label' => t("{$prefix}.roles_column_priority"), 'sort_by' => 'priority'],
        ];
    }

    /**
     * @param UsimRole $item
     * @return array{_model_id: int|string, name: string, guard_name: string, home_screen: string, priority: mixed}
     */
    protected function formatRow(object $item): array
    {
        /** @var UsimRole $item */
        $homeScreen = str_replace('App\\UI\\Screens\\', '', (string) $item->home_screen);

        return [
            '_model_id' => $item->id,
            'name' => t("role.{$item->name}.name"),
            'guard_name' => (string) $item->guard_name,
            'home_screen' => $homeScreen,
            'priority' => $item->priority,
        ];
    }
}
