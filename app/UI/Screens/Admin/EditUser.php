<?php

// @usim: feature="admin", type="screen"

namespace App\UI\Screens\Admin;

use App\Models\User;
use App\Services\Role\RoleService;
use App\UI\Screens\Admin\Presenters\UserEditDialogPresenter;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Contracts\UserMutationServiceInterface;
use Idei\Usim\Contracts\UsimUserInterface;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Screen for editing user details, roles, memberships, and status.
 *
 * @phpstan-type UserPayload array{
 *     id: int|string,
 *     name: string,
 *     email: string,
 *     roles?: list<array{name?: string}|string>,
 *     email_verified_at?: mixed,
 *     is_in_lobby?: bool,
 *     has_operational_units?: bool,
 *     active_unit?: array{id: int|string, slug: string, name: string}|null,
 *     operational_units?: list<array{id: int|string, slug: string, name: string}>,
 *     units_with_roles?: array<string, list<string>>
 * }
 */
class EditUser extends Screen
{
    public const DEFAULT_SUBMIT_ACTION = 'submit_update_user';

    public const DEFAULT_CANCEL_ACTION = 'close_edit_user';

    public const DELETE_ACTION = 'delete_user';

    private const SYSTEM_REGISTERED_ROLE = 'registered';

    private const DEFAULT_FALLBACK_ROLE = 'publisher';

    private const EXCLUDED_MEMBERSHIP_SLUGS = ['lobby', 'main'];

    private const CONTAINER_PADDING = 20;

    private const BUTTONS_GAP = 10;

    private const BUTTONS_PADDING = 10;

    public static Visibility $visibility = Visibility::AUTHENTICATED;

    protected Label $lbl_edit_user_result;

    public function __construct(
        protected UserMutationServiceInterface $userService,
        protected RoleService $roleService,
        protected ?UserEditDialogPresenter $userEditDialogPresenter = null,
    ) {
        $this->userEditDialogPresenter = $userEditDialogPresenter ?? new UserEditDialogPresenter;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $rawUser = $params['user'] ?? null;
        $rawUserId = $params['user_id'] ?? $params['id'] ?? null;

        /** @var array<string, mixed>|null $user */
        $user = $this->resolveUserPayload($rawUser, $rawUserId);

        $isInLobby = ($user !== null) && ! empty($user['is_in_lobby']);
        $hasOperationalUnits = ($user !== null) && ! empty($user['has_operational_units']);
        $activeUnit = $this->resolveActiveUnit($user, $hasOperationalUnits);
        $activeSlug = $activeUnit['slug'] ?? null;
        $selectedRoles = $this->resolveSelectedRoles($user, $activeSlug, $isInLobby);
        $emailVerified = $this->isEmailVerified($user);

        $wrapper = UI::container('edit_user_wrapper')
            ->maxWidth(Size::px(640))
            ->width(Size::full())
            ->centerHorizontal();

        $card = UI::container('edit_user_dialog')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->padding(Spacing::px(self::CONTAINER_PADDING));

        if ($this->isOpenedAsModal()) {
            $header = UI::container('edit_user_modal_header')
                ->layout(LayoutType::HORIZONTAL)
                ->justifyContent(JustifyContent::SPACE_BETWEEN)
                ->alignItems(AlignItems::CENTER)
                ->plain()
                ->shadow(false)
                ->padding(Spacing::zero())
                ->margin(Spacing::px(5));

            $header->add(
                UI::label('lbl_title')
                    ->text(self::trans('title'))
                    ->style('h3')
            );

            $header->add(
                UI::button('btn_close_modal')
                    ->label('✕')
                    ->action('close_edit_user')
                    ->style('secondary')
                    ->variant('ghost')
                    ->plain()
            );

            $card->add($header);
        } else {
            $card->add(
                UI::label('lbl_title')
                    ->text(self::trans('title'))
                    ->style('h2')
                    ->center()
            );
        }

        if ($isInLobby) {
            $this->buildLobbyBanner($card);
        }

        $this->buildHiddenInputs($card, $user, $isInLobby);

        $name = $user !== null ? $this->normalizeStringValue($user['name'] ?? null, '') : '';
        $email = $user !== null ? $this->normalizeStringValue($user['email'] ?? null, '') : '';
        $this->buildUserInputs($card, $name, $email);

        if ($hasOperationalUnits && $activeUnit !== null) {
            $this->buildActiveUnitSection($card, $activeUnit);
        }

        if ($user !== null && ! empty($user['units_with_roles']) && is_array($user['units_with_roles'])) {
            /** @var array<string, list<string>> $unitsWithRoles */
            $unitsWithRoles = $user['units_with_roles'];
            $this->buildOtherMembershipsSection($card, $unitsWithRoles, $activeSlug);
        }

        $this->buildRoleCheckboxes($card, $selectedRoles);
        $this->buildEmailCheckboxes($card, $emailVerified);

        $this->lbl_edit_user_result = UI::label('lbl_edit_user_result')->text('');
        $card->add($this->lbl_edit_user_result);

        $submitLabel = $this->resolveSubmitLabel($isInLobby, $hasOperationalUnits, $activeUnit);
        $this->buildActionButtons(
            $card,
            submitAction: self::DEFAULT_SUBMIT_ACTION,
            cancelAction: self::DEFAULT_CANCEL_ACTION,
            submitLabel: $submitLabel,
            canDelete: $user !== null && ! empty($user['id'])
        );

        $wrapper->add($card);
        $container->add($wrapper);
    }

    protected function postLoadUI(): void
    {
        if (isset($this->lbl_edit_user_result)) {
            $this->lbl_edit_user_result->text('')->style('');
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onSubmitUpdateUser(array $params): void
    {
        $user = $this->resolveTargetUserForAction($params, updateResultLabel: true);
        if (! $user) {
            return;
        }

        $userId = (int) $user->getKey();
        $updateData = $params;
        if (isset($updateData['roles'])) {
            $updateData['roles'] = (array) $updateData['roles'];
        }

        if (isset($params['target_unit']) && (is_int($params['target_unit']) || is_numeric($params['target_unit']))) {
            $updateData['target_unit'] = (int) $params['target_unit'];
        }

        $response = $this->userService->updateUser($user, $updateData);
        $status = $response['status'];
        $message = $response['message'];

        if ($status === 'success') {
            // fire an event or perform additional actions if needed
            event(new UsimEvent(
                'user_updated',
                [
                    'user_id' => $userId,
                    'data' => $updateData,
                ]
            ));

            $this->setResultFeedback($message, 'success');

            if ($this->isOpenedAsModal()) {
                $this->closeModal();
            }

            return;
        }

        $this->setResultFeedback($message, 'error');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onDeleteUser(array $params): void
    {
        $user = $this->resolveTargetUserForAction($params);
        if (! $user) {
            return;
        }

        $userId = (int) $user->getKey();
        $rawName = $user instanceof UsimUserInterface
            ? $user->getDisplayName()
            : $user->getAttribute('name');
        $userName = is_string($rawName) ? $rawName : '';

        ConfirmDialog::open(
            caller: $this,
            type: DialogType::WARNING,
            title: self::trans('delete_confirm_title'),
            message: self::trans('delete_confirm_message', ['name' => $userName]),
            confirmAction: 'confirm_delete_user',
            confirmParams: ['user_id' => $userId],
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onConfirmDeleteUser(array $params): void
    {
        $user = $this->resolveTargetUserForAction($params);
        if (! $user) {
            return;
        }

        $userId = (int) $user->getKey();
        $response = $this->userService->deleteUser($user);
        $status = $response['status'];
        $message = $response['message'];

        $this->toast($message, $status);

        event(new UsimEvent(
            'user_deleted',
            [
                'user_id' => $userId,
            ]
        ));

        $this->closeModal();
    }

    /**
     * Resolve and validate the target user for mutation actions.
     *
     * @param  array<string, mixed>  $params
     */
    private function resolveTargetUserForAction(array $params, bool $updateResultLabel = false): ?Model
    {
        $rawId = $params['user_id'] ?? null;
        $userId = (is_int($rawId) || is_string($rawId)) ? (int) $rawId : 0;

        if ($userId <= 0) {
            $this->setResultFeedback(self::trans('user_id_required'), 'error', $updateResultLabel);

            return null;
        }

        $user = $this->userService->findUser($userId);
        if (! $user) {
            $this->setResultFeedback(self::trans('user_not_found'), 'error', $updateResultLabel);

            return null;
        }

        return $user;
    }

    /**
     * Display toast and optionally update the result label.
     */
    private function setResultFeedback(string $message, string $style, bool $updateLabel = true): void
    {
        $this->toast($message, $style);
        if ($updateLabel && isset($this->lbl_edit_user_result)) {
            $this->lbl_edit_user_result->text($message)->style($style);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onCloseEditUser(array $params = []): void
    {
        $this->closeModal();
    }

    /**
     * Alias for closing modal
     *
     * @param  array<string, mixed>  $params
     */
    public function onCloseModal(array $params = []): void
    {
        $this->onCloseEditUser($params);
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    private static function trans(string $key, array $replace = []): string
    {
        $screenKey = "screens.auth.edit_user.{$key}";
        $translated = t($screenKey, $replace);
        if ($translated !== $screenKey) {
            return $translated;
        }

        $modalKey = "modal.edit_user.{$key}";
        $modalTranslated = t($modalKey, $replace);
        if ($modalTranslated !== $modalKey) {
            return $modalTranslated;
        }

        return $translated;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveUserPayload(mixed $rawUser, mixed $rawUserId): ?array
    {
        if (is_array($rawUser)) {
            /** @var array<string, mixed> $rawUser */
            return $rawUser;
        }

        $userId = null;
        if ($rawUser instanceof User) {
            $userId = (int) $rawUser->id;
        } elseif ((is_int($rawUserId) || is_string($rawUserId)) && (int) $rawUserId > 0) {
            $userId = (int) $rawUserId;
        }

        if ($userId === null) {
            $authUser = Auth::user();
            if ($authUser instanceof User) {
                $userId = (int) $authUser->id;
            }
        }

        if ($userId === null) {
            return null;
        }

        $response = $this->userService->getUser($userId);
        if ($response['status'] !== 'success' || empty($response['data'])) {
            return null;
        }

        /** @var array<string, mixed> $userData */
        $userData = $response['data'];
        $presenter = $this->userEditDialogPresenter ?? new UserEditDialogPresenter;
        $presented = $presenter->present($userData);

        return $presented ?? $userData;
    }

    private function buildLobbyBanner(Container $container): void
    {
        $container->add(
            UI::label('lobby_banner')
                ->text(self::trans('lobby_banner'))
                ->style('warning')
                ->size('medium')
        );
    }

    /**
     * @param  array<string, mixed>|null  $user
     */
    private function buildHiddenInputs(Container $container, ?array $user, bool $isInLobby): void
    {
        $userId = $user !== null ? $this->normalizeStringValue($user['id'] ?? null, '') : '';

        $container->add(
            UI::input('user_id')
                ->type('hidden')
                ->value($userId)
        );

        $container->add(
            UI::input('is_in_lobby')
                ->type('hidden')
                ->value($isInLobby ? '1' : '0')
        );
    }

    private function buildUserInputs(Container $container, string $name, string $email): void
    {
        $container->add(
            UI::input('name')
                ->label(self::trans('name'))
                ->placeholder(self::trans('name_placeholder'))
                ->required(true)
                ->value($name)
                ->autocomplete('off')
        );

        $container->add(
            UI::input('email')
                ->label(self::trans('email'))
                ->placeholder(self::trans('email_placeholder'))
                ->required(true)
                ->value($email)
                ->autocomplete('off')
        );
    }

    /**
     * @param  array{id: int|string, slug: string, name: string}  $activeUnit
     */
    private function buildActiveUnitSection(Container $container, array $activeUnit): void
    {
        $container->add(
            UI::label('active_unit_badge')
                ->text(self::trans('active_unit_label', ['unit' => $activeUnit['name']]))
                ->style('primary')
                ->size('medium')
        );

        $container->add(
            UI::label('active_unit_help')
                ->text(self::trans('active_unit_help'))
                ->style('muted')
                ->size('small')
        );

        $container->add(
            UI::input('target_unit')
                ->type('hidden')
                ->value((string) $activeUnit['id'])
        );
    }

    /**
     * @param  array<string, list<string>>  $unitsWithRoles
     */
    private function buildOtherMembershipsSection(
        Container $container,
        array $unitsWithRoles,
        ?string $activeSlug
    ): void {
        $memberships = $this->formatOtherMemberships($unitsWithRoles, $activeSlug);
        if (empty($memberships)) {
            return;
        }

        $container->add(
            UI::label('other_units_info')
                ->text(self::trans('other_units').': '.implode(' | ', $memberships))
                ->style('secondary')
                ->size('small')
        );
    }

    /**
     * @param  array<string, list<string>>  $unitsWithRoles
     * @return list<string>
     */
    private function formatOtherMemberships(array $unitsWithRoles, ?string $activeSlug): array
    {
        $relevantSlugs = [];
        foreach (array_keys($unitsWithRoles) as $slugKey) {
            $slug = (string) $slugKey;
            if ($slug !== $activeSlug && ! in_array($slug, self::EXCLUDED_MEMBERSHIP_SLUGS, true)) {
                $relevantSlugs[] = $slug;
            }
        }

        if (empty($relevantSlugs)) {
            return [];
        }

        /** @var \Illuminate\Database\Eloquent\Collection<string, UsimUnit>|Collection<string, UsimUnit> $unitModels */
        $unitModels = UsimUnit::whereIn('slug', $relevantSlugs)->get()->keyBy('slug');

        $memberships = [];
        foreach ($relevantSlugs as $slug) {
            $rolesList = $unitsWithRoles[$slug] ?? [];
            $unitModel = $unitModels->get($slug);
            $unit = $unitModel instanceof UsimUnit ? $unitModel : null;
            $unitName = $this->resolveUnitDisplayName($unit, $slug);

            $rolesTranslated = array_map(
                static fn (string $role): string => t("role.{$role}.name"),
                $rolesList
            );

            $rolesText = empty($rolesTranslated) ? t('role.none') : implode(', ', $rolesTranslated);
            $memberships[] = "{$unitName} ({$rolesText})";
        }

        return $memberships;
    }

    private function resolveUnitDisplayName(?UsimUnit $unit, string $slug): string
    {
        if ($unit !== null) {
            $displayName = (string) $unit->display_name;
            $translationKey = (string) $unit->translation_key;

            if ($displayName !== '' && $displayName !== $translationKey) {
                return $displayName;
            }
        }

        return ucfirst($slug);
    }

    private function normalizeStringValue(mixed $value, string $fallback = ''): string
    {
        if (is_int($value) || is_float($value) || is_bool($value) || is_string($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return $fallback;
    }

    /**
     * @param  list<string>  $selectedRoles
     */
    private function buildRoleCheckboxes(Container $container, array $selectedRoles): void
    {
        $container->add(
            UI::checkbox('roles')
                ->label(self::trans('roles'))
                ->options($this->resolveRoleOptions())
                ->vertical()
                ->selectedValues($selectedRoles)
                ->required(true)
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function resolveRoleOptions(): array
    {
        $roles = $this->roleService->getRolesForActor(User::class);

        /** @var list<array{value: string, label: string}> $roleOptions */
        $roleOptions = array_map(static fn (UsimRole $role): array => [
            'value' => (string) $role->name,
            'label' => t("role.{$role->name}.name"),
        ], $roles);

        if (empty($roleOptions)) {
            return [
                ['value' => 'user', 'label' => t('role.user.name')],
                ['value' => 'admin', 'label' => t('role.admin.name')],
            ];
        }

        return $roleOptions;
    }

    private function buildEmailCheckboxes(Container $container, bool $emailVerified): void
    {
        $container->add(
            UI::checkbox('send_reset_email')
                ->label(self::trans('send_reset_email'))
                ->checked(false)
        );

        if (! $emailVerified) {
            $container->add(
                UI::checkbox('send_verification_email')
                    ->label(self::trans('send_verification_email'))
                    ->checked(false)
            );
        }
    }

    private function buildActionButtons(
        Container $container,
        string $submitAction,
        string $cancelAction,
        string $submitLabel,
        bool $canDelete
    ): void {
        $buttonsContainer = UI::container('edit_user_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->shadow(false)
            ->plain()
            ->gap(Spacing::px(self::BUTTONS_GAP))
            ->padding(Spacing::each(Spacing::px(self::BUTTONS_PADDING)));

        $buttonsContainer->add(
            UI::button('btn_cancel_register')
                ->label(self::trans('cancel'))
                ->style('secondary')
                ->action($cancelAction)
        );

        $buttonsContainer->add(
            UI::button('btn_submit_register')
                ->label($submitLabel)
                ->style('primary')
                ->action($submitAction)
        );

        $buttonsContainer->add(
            UI::button('btn_delete_user')
                ->label(self::trans('delete_user'))
                ->style('danger')
                ->action(self::DELETE_ACTION)
                ->visible($canDelete)
        );

        $container->add($buttonsContainer);
    }

    /**
     * @param  array{id: int|string, slug: string, name: string}|null  $activeUnit
     */
    private function resolveSubmitLabel(bool $isInLobby, bool $hasOperationalUnits, ?array $activeUnit): string
    {
        if (! $isInLobby) {
            return self::trans('update_user');
        }

        if ($hasOperationalUnits && $activeUnit !== null && $activeUnit['name'] !== '') {
            return self::trans('approve_in_unit', ['unit' => $activeUnit['name']]);
        }

        return self::trans('approve_simple');
    }

    /**
     * @param  array<string, mixed>|null  $user
     * @return array{id: int|string, slug: string, name: string}|null
     */
    private function resolveActiveUnit(?array $user, bool $hasOperationalUnits): ?array
    {
        if ($user === null) {
            return null;
        }

        $activeUnit = $user['active_unit'] ?? null;
        if (is_array($activeUnit) && isset($activeUnit['slug'], $activeUnit['name'])) {
            return [
                'id' => $activeUnit['id'] ?? 0,
                'slug' => (string) $activeUnit['slug'],
                'name' => (string) $activeUnit['name'],
            ];
        }

        if ($hasOperationalUnits) {
            $operationalUnits = $user['operational_units'] ?? null;
            if (is_array($operationalUnits) && isset($operationalUnits[0]) && is_array($operationalUnits[0])) {
                $firstOperationalUnit = $operationalUnits[0];
                if (isset($firstOperationalUnit['slug'], $firstOperationalUnit['name'])) {
                    return [
                        'id' => $firstOperationalUnit['id'] ?? 0,
                        'slug' => (string) $firstOperationalUnit['slug'],
                        'name' => (string) $firstOperationalUnit['name'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $user
     * @return list<string>
     */
    private function resolveSelectedRoles(?array $user, ?string $activeSlug, bool $isInLobby): array
    {
        /** @var list<string> $selectedRoles */
        $selectedRoles = [];

        if ($user !== null) {
            $unitsWithRoles = $user['units_with_roles'] ?? null;
            if ($activeSlug !== null && is_array($unitsWithRoles) && isset($unitsWithRoles[$activeSlug]) && is_array($unitsWithRoles[$activeSlug])) {
                $selectedRoles = $this->normalizeStringList($unitsWithRoles[$activeSlug]);
            } elseif (! $isInLobby && is_array($user['roles'] ?? null)) {
                $selectedRoles = $this->extractRoleNames($user['roles']);
            }
        }

        if ($isInLobby) {
            $filteredRoles = [];
            foreach ($selectedRoles as $role) {
                if ($role !== self::SYSTEM_REGISTERED_ROLE) {
                    $filteredRoles[] = $role;
                }
            }

            $selectedRoles = $filteredRoles;
        }

        return $selectedRoles !== [] ? $selectedRoles : [self::DEFAULT_FALLBACK_ROLE];
    }

    /**
     * @param  array<mixed>  $roles
     * @return list<string>
     */
    private function extractRoleNames(array $roles): array
    {
        /** @var list<string> $names */
        $names = [];
        foreach ($roles as $role) {
            if (is_array($role) && isset($role['name'])) {
                $roleName = $role['name'];
                if (is_string($roleName) && $roleName !== '') {
                    $names[] = (string) $roleName;
                }
            } elseif (is_string($role) && $role !== '') {
                $names[] = (string) $role;
            }
        }

        return $names;
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function normalizeStringList(array $values): array
    {
        /** @var list<string> $normalized */
        $normalized = [];

        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $user
     */
    private function isEmailVerified(?array $user): bool
    {
        return $user !== null && ($user['email_verified_at'] ?? null) !== null;
    }
}
