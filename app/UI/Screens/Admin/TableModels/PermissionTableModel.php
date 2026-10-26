<?php

// @usim: feature="admin", type="screen"

namespace App\UI\Screens\Admin\TableModels;

use App\Services\Permissions\PermissionListingService;
use Idei\Usim\DataTable\AbstractListingTableModel;
use Spatie\Permission\Models\Permission;

/**
 * Table model for managing permissions listing.
 *
 * @extends AbstractListingTableModel<Permission>
 */
class PermissionTableModel extends AbstractListingTableModel
{
    protected function resolveListingService(): PermissionListingService
    {
        return app(PermissionListingService::class);
    }

    public function getColumns(): array
    {
        return [
            'name' => [
                'label' => t('screens.admin.users_manager.permissions_column_name'),
                'width' => 100,
                'sort_by' => 'name',
            ],
            'description' => [
                'label' => t('screens.admin.users_manager.permissions_column_description'),
                'width' => 200,
            ],
        ];
    }

    /**
     * @param  Permission  $item
     * @return array{_model_id: int|string, name: string, description: string}
     */
    protected function formatRow(object $item): array
    {
        /** @var Permission $item */
        return [
            '_model_id' => $item->id,
            'name' => t("permission.{$item->name}.name"),
            'description' => t("permission.{$item->name}.description"),
        ];
    }
}
