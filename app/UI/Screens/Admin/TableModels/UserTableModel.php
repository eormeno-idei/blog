<?php

// @usim: feature="admin", type="screen"

namespace App\UI\Screens\Admin\TableModels;

use App\Models\User;
use App\Services\Units\UsimUnitsService;
use Idei\Usim\Components\Table;
use Idei\Usim\Contracts\UserListingServiceInterface;
use Idei\Usim\DataTable\AbstractListingTableModel;

/**
 * Table model for managing users listing with adaptive multi-unit display support.
 *
 * @extends AbstractListingTableModel<User>
 */
class UserTableModel extends AbstractListingTableModel
{
    protected UsimUnitsService $unitsService;

    public function __construct(Table $tableBuilder, ?UsimUnitsService $unitsService = null)
    {
        $this->unitsService = $unitsService ?? app(UsimUnitsService::class);
        parent::__construct($tableBuilder);
    }

    /**
     * @return UserListingServiceInterface<User>
     */
    protected function resolveListingService(): UserListingServiceInterface
    {
        /** @var UserListingServiceInterface<User> $service */
        $service = app(UserListingServiceInterface::class);

        return $service;
    }

    public function getColumns(): array
    {
        $prefix = 'screens.admin.users_manager.table';
        $hasOperationalUnits = $this->unitsService->hasOperationalUnits();

        if ($hasOperationalUnits) {
            return [
                'name' => ['label' => t("{$prefix}.name"), 'width' => 200, 'sort_by' => 'name'],
                'email' => ['label' => t("{$prefix}.email"), 'width' => 200, 'sort_by' => 'email'],
                'email_verified' => ['label' => t("{$prefix}.email_verified"), 'width' => 80, 'sort_by' => 'email_verified_at'],
                'units' => ['label' => t("{$prefix}.units"), 'width' => 160],
                'roles' => ['label' => t("{$prefix}.roles"), 'width' => 180, 'sort_by' => 'role_name'],
            ];
        }

        return [
            'name' => ['label' => t("{$prefix}.name"), 'width' => 250, 'sort_by' => 'name'],
            'email' => ['label' => t("{$prefix}.email"), 'width' => 230, 'sort_by' => 'email'],
            'email_verified' => ['label' => t("{$prefix}.email_verified"), 'width' => 100, 'sort_by' => 'email_verified_at'],
            'roles' => ['label' => t("{$prefix}.roles"), 'width' => 220, 'sort_by' => 'role_name'],
        ];
    }

    /**
     * @param  User  $item
     * @return array<string, mixed>
     */
    protected function formatRow(object $item): array
    {
        /** @var User $user */
        $user = $item;
        $hasOperationalUnits = $this->unitsService->hasOperationalUnits();
        $roles = $this->formatRoles($user, $hasOperationalUnits);
        $emailVerified = $user->email_verified_at ?? false;

        $row = [
            '_model_id' => $user->id,
            'name' => $user->name ?? '',
            'email' => $user->email ?? '',
            'email_verified' => $emailVerified ? '✅' : '⚠️',
        ];

        if ($hasOperationalUnits) {
            $row['units'] = $this->formatUnits($user);
        }

        $row['roles'] = $roles;

        return $row;
    }

    private function formatUnits(User $user): string
    {
        $units = $user->relationLoaded('usimUnits') ? $user->usimUnits : $user->usimUnits()->get();

        $operationalUnits = $units->filter(static function ($unit): bool {
            return $unit->type !== 'system' && ! in_array($unit->slug, ['main', 'lobby'], true);
        })->values();

        if ($operationalUnits->isNotEmpty()) {
            $first = $operationalUnits->first();
            $firstName = ($first->display_name !== $first->translation_key)
                ? $first->display_name
                : ucfirst($first->slug);

            $extraCount = $operationalUnits->count() - 1;
            if ($extraCount > 0) {
                return "{$firstName} (+{$extraCount})";
            }

            return $firstName;
        }

        $lobby = $units->firstWhere('slug', 'lobby');
        if ($lobby) {
            return ($lobby->display_name !== $lobby->translation_key)
                ? $lobby->display_name
                : 'Lobby';
        }

        $main = $units->firstWhere('slug', 'main');
        if ($main) {
            return ($main->display_name !== $main->translation_key)
                ? $main->display_name
                : ucfirst($main->slug);
        }

        return '-';
    }

    private function formatRoles(User $user, bool $hasOperationalUnits): string
    {
        $rolesCollection = $user->globalRoles->isNotEmpty()
            ? $user->globalRoles
            : $user->roles;

        /** @var list<string> $roleNames */
        $roleNames = $rolesCollection
            ->pluck('name')
            ->filter(static fn ($name): bool => is_string($name))
            ->unique()
            ->values()
            ->toArray();

        // In Simple Mode (only system units main and lobby exist), users in lobby show Waiting (Registered)
        if (! $hasOperationalUnits) {
            if ($user->isRegisteredInLobby()) {
                return t('role.registered.waiting');
            }
        }

        /** @var list<string> $roles */
        $roles = array_map(static fn (string $name): string => t("role.$name.name"), $roleNames);

        return $roles ? implode(', ', $roles) : t('role.none');
    }
}
