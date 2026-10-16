<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Admin\Concerns;

use App\Services\Role\RoleService;
use App\UI\Screens\Admin\TableModels\PermissionTableModel;
use App\UI\Screens\Admin\TableModels\RoleTableModel;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Split;
use Idei\Usim\Components\Table;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Trait to manage the roles and permissions UI section and table events.
 *
 * @property RoleService $roleService
 */
trait ManagesRolesSection
{
    protected const string ROLES_I18N_PREFIX = 'screens.admin.users_manager.';

    protected Table $roles_table;
    protected Table $permissions_table;
    protected Split $roles_split;

    protected function buildRolesContainer(): Container
    {
        $rolesContainer = UI::container('roles_container')
            ->card()
            ->layout(LayoutType::VERTICAL)
            ->fullWidth()
            ->gap(Spacing::px(2))
            ->minHeight(Size::px(550));

        $rolesTable = UI::table('roles_table')
            ->dataModel(RoleTableModel::class)
            ->selectionMode(SelectionMode::SINGLE)
            ->fitContainer(availableHeight: 546, hasToolbar: false, paginated: true);

        $permissionsTable = UI::table('permissions_table')
            ->dataModel(PermissionTableModel::class)
            ->selectionMode(SelectionMode::MULTIPLE)
            ->fitContainer(availableHeight: 546, hasToolbar: false, paginated: false);

        $rolesSplit = UI::split('roles_split')
            ->horizontal()
            ->width(Size::full())
            ->height(Size::px(550))
            ->minFirstSize('380px')
            ->minSecondSize('450px')
            ->splitSize('40%')
            ->addFirst($rolesTable)
            ->addSecond($permissionsTable);

        $rolesContainer->add($rolesSplit);

        return $rolesContainer;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onRolesTableColumnClicked(array $params): void
    {
        $this->handleTableSort($this->roles_table, $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onPermissionsTableColumnClicked(array $params): void
    {
        $this->handleTableSort($this->permissions_table, $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onRolesTableRowClicked(array $params): void
    {
        if (!$this->userCan('manage.roles')) {
            $this->toast(t('You don\'t have permission to manage roles'), 'error');
            return;
        }

        $roleId = $this->selectableId($params, 'model_id');
        if ($roleId === null) {
            $this->toast(t('Role ID is required'), 'error');
            return;
        }

        $this->roles_table->select($roleId);

        $permissionIds = $this->roleService->getPermissionIds($roleId);
        $this->permissions_table->select($permissionIds);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onPermissionsTableRowClicked(array $params): void
    {
        if (!$this->userCan('manage.roles')) {
            $this->toast(t('You don\'t have permission to manage roles'), 'error');
            return;
        }

        $selectedRole = $this->roles_table->select();
        $roleId = is_array($selectedRole) ? ($selectedRole[0] ?? null) : $selectedRole;

        if ($roleId instanceof Table || $roleId === null || $roleId === '') {
            $this->toast(t(self::ROLES_I18N_PREFIX . 'role_selection_warning'), 'warning');
            return;
        }

        $permissionId = $this->selectableId($params, 'model_id');
        if ($permissionId === null) {
            $this->toast(t('Permission ID is required'), 'error');
            return;
        }

        $this->roleService->togglePermission($roleId, $permissionId);

        $permissionIds = $this->roleService->getPermissionIds($roleId);
        $this->permissions_table->select($permissionIds);
    }
}
