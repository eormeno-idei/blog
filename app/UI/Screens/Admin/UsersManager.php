<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Admin;

use App\Services\Device\DeviceService;
use App\Services\Role\RoleService;
use App\UI\Screens\Admin\Concerns\HandlesModalFeedback;
use App\UI\Screens\Admin\Concerns\HandlesScreenParameters;
use App\UI\Screens\Admin\Concerns\ManagesDevicesSection;
use App\UI\Screens\Admin\Concerns\ManagesRolesSection;
use App\UI\Screens\Admin\Concerns\ManagesUsersSection;
use App\UI\Screens\Admin\Concerns\ResolvesActiveUnitContext;
use App\UI\Screens\Admin\Presenters\UserEditDialogPresenter;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Table;
use Idei\Usim\Contracts\RegisterActionInterface;
use Idei\Usim\Contracts\UserMutationServiceInterface;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Screen orchestrator for administration of users, devices, and roles.
 */
class UsersManager extends Screen
{
    use HandlesModalFeedback;
    use HandlesScreenParameters;
    use ManagesDevicesSection;
    use ManagesRolesSection;
    use ManagesUsersSection;
    use ResolvesActiveUnitContext;

    private const I18N_PREFIX = 'screens.admin.users_manager.';

    protected Container $tabs_container;

    protected DeviceService $deviceService;

    /**
     * @var Screen|null The current screen instance, used for context in building UI components.
     */
    protected ?Screen $screen = null;

    public function __construct(
        protected RegisterActionInterface $registerService,
        protected UserMutationServiceInterface $userService,
        protected RoleService $roleService,
        ?DeviceService $deviceService = null,
        protected ?UserEditDialogPresenter $userEditDialogPresenter = null,
    ) {
        $this->deviceService = $deviceService ?? app(DeviceService::class);
        $this->userEditDialogPresenter = $userEditDialogPresenter ?? new UserEditDialogPresenter;
        $this->screen = $this;
    }

    public static function authorize(): bool
    {
        return self::requirePermission('admin.users_manager.access');
    }

    public static function getMenuLabel(): string
    {
        return t(self::I18N_PREFIX.'menu_label');
    }

    public static function getMenuIcon(): ?string
    {
        return '🛠️';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->plain()
            ->fullWidth()
            ->maxWidth(Size::px(1280))
            ->padding(Spacing::px(0))
            ->centerHorizontal();

        $this->tabs_container = UI::container('tabs_container')
            ->width(Size::full())
            ->padding(Spacing::px(0))
            ->minHeight(Size::px(620))
            ->rounded(0)
            ->gap(Spacing::px(2))
            ->tabs(
                [
                    'users_tab' => ['label' => t(self::I18N_PREFIX.'users_tab')],
                    'devices_tab' => ['label' => t(self::I18N_PREFIX.'devices_tab')],
                    'roles_tab' => [
                        'label' => t(self::I18N_PREFIX.'roles_tab'),
                        'disabled' => ! $this->userCan('manage.roles'),
                    ],
                ],
                'users_tab'
            );

        $this->tabs_container->add($this->buildUsersCrudContainer(), tab: 'users_tab');
        $this->tabs_container->add($this->buildDevicesCrudContainer(), tab: 'devices_tab');
        $this->tabs_container->add($this->buildRolesContainer(), tab: 'roles_tab');
        $container->add($this->tabs_container);
    }

    /**
     * Generic table sorting helper (DRY).
     *
     * @param  array<string, mixed>  $params
     */
    protected function handleTableSort(Table $table, array $params): void
    {
        $column = $this->optionalStringParam($params, 'sort_by');
        if ($column === null || $column === '') {
            return;
        }

        $table->sortedBy($column);
        $table->page(1);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onChangePage(array $params): void
    {
        $page = $this->intParamOrDefault($params, 'page', 1);
        $rawCompId = $this->optionalIntParam($params, '_component_id')
            ?? request()->input('component_id');
        $componentId = is_numeric($rawCompId) ? (int) $rawCompId : null;

        if (isset($this->devices_table) && $componentId !== null && $componentId === $this->devices_table->getId()) {
            $this->devices_table->page($page);

            return;
        }

        if (isset($this->roles_table) && $componentId !== null && $componentId === $this->roles_table->getId()) {
            $this->roles_table->page($page);

            return;
        }

        $this->users_table->page($page);
    }
}
