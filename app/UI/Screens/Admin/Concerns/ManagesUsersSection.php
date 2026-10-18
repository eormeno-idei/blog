<?php

// @usim: feature="admin", type="screen"

namespace App\UI\Screens\Admin\Concerns;

use App\UI\Screens\Admin\EditUser;
use App\UI\Screens\Admin\Presenters\UserEditDialogPresenter;
use App\UI\Screens\Admin\TableModels\UserTableModel;
use App\UI\Screens\Auth\Register;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Table;
use Idei\Usim\Contracts\UserMutationServiceInterface;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Trait to manage the users CRUD UI section, table events, and dialog workflows.
 *
 * @mixin Screen
 *
 * @property UserMutationServiceInterface $userService
 * @property ?UserEditDialogPresenter $userEditDialogPresenter
 */
trait ManagesUsersSection
{
    protected const string USERS_I18N_PREFIX = 'screens.admin.users_manager.';

    protected Table $users_table;

    protected Input $search_users;

    protected Button $add_user_btn;

    protected function buildUsersCrudContainer(): Container
    {
        $usersCrudContainer = UI::container('users_crud_container')
            ->layout(LayoutType::VERTICAL)
            ->fullWidth()
            ->gap(Spacing::px(4))
            ->rounded(0)
            ->minHeight(Size::px(550))
            ->plain();

        $toolbar = UI::container('users_toolbar')
            ->layout(LayoutType::HORIZONTAL)
            ->fullWidth()
            ->padding(Spacing::px(8))
            ->gap(Spacing::px(8));

        $search = UI::input('search_users')
            ->placeholder(t(self::USERS_I18N_PREFIX.'search_placeholder'))
            ->width(Size::px(300))
            ->autocomplete('off')
            ->onInput('search_users', [])
            ->debounce(500);

        $addBtn = UI::button('add_user_btn')
            ->label(t(self::USERS_I18N_PREFIX.'add_user'))
            ->style('secondary')
            ->action('add_user_clicked')
            ->icon('plus');

        $toolbar->add($search)->add($addBtn);

        $usersTable = UI::table('users_table')
            ->dataModel(UserTableModel::class)
            ->selectionMode(SelectionMode::SINGLE)
            ->sortedBy('name')
            ->fitContainer(
                availableHeight: 550,
                rowHeight: 45,
                hasToolbar: true,
                paginated: true
            );

        $usersCrudContainer
            ->add($toolbar)
            ->add($usersTable);

        return $usersCrudContainer;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onAddUserClicked(array $params): void
    {
        Screen::openAsModal(
            params: [
                'fakeData' => config('app.env') === 'local',
                'askForRole' => true,
            ],
            caller: $this->screen,
            screenClass: Register::class
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onUsersTableColumnClicked(array $params): void
    {
        $this->handleTableSort($this->users_table, $params);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onSearchUsers(array $params): void
    {
        $search = trim($this->searchParam($params, ['value', 'search_users']));
        $this->users_table->setSearchTerm($search);
        $this->search_users->value($search);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onUsersTableRowClicked(array $params): void
    {
        $userId = $this->optionalIntParam($params, 'model_id');
        if ($userId === null) {
            $this->toast(t('User ID is required'), 'error');

            return;
        }

        $response = $this->userService->getUser($userId);
        if ($response['status'] !== 'success' || empty($response['data'])) {
            $this->toast($response['message'], 'error');

            return;
        }

        $this->users_table->select($userId);

        $activeUnit = $this->resolveActiveUnit();
        $presenter = $this->userEditDialogPresenter ?? new UserEditDialogPresenter;
        $modalUser = $presenter->present($response['data'], $activeUnit);
        if ($modalUser === null) {
            $this->toast(t('User not found'), 'error');

            return;
        }

        Screen::openAsModal(
            params: [
                'user' => $modalUser,
            ],
            caller: $this->screen,
            screenClass: EditUser::class
        );
    }

    /**
     * Handles the 'user_created' event, fired by: Register::class.
     *
     * @param  array<string, mixed>  $params
     */
    public function onUserCreated(array $params): void
    {
        $this->users_table->refresh();
    }

    /**
     * Handles the 'user_updated' event, fired by: EditUser:class.
     *
     * @param  array<string, mixed>  $params
     */
    public function onUserUpdated(array $params): void
    {
        $this->users_table->refresh();
    }

    /**
     * Handles the 'user_deleted' event, fired by: EditUser:class.
     *
     * @param  array<string, mixed>  $params
     */
    public function onUserDeleted(array $params): void
    {
        $this->users_table->refresh();
    }

    /**
     * Legacy proxy for backwards compatibility.
     *
     * @deprecated Use UserEditDialogPresenter::present() instead.
     *
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>|null
     */
    protected function editDialogUser(array $user): ?array
    {
        $presenter = $this->userEditDialogPresenter ?? new UserEditDialogPresenter;

        return $presenter->present($user);
    }
}
