<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Admin\Presenters;

use Idei\Usim\Models\UsimUnit;

/**
 * Presenter responsible for formatting user entity data for EditUserDialog modal.
 */
class UserEditDialogPresenter
{
    /**
     * Format and normalize user array structure for EditUserDialog.
     *
     * @param array<string, mixed> $user
     * @param UsimUnit|null $activeUnit
     * @return array{
     *     id: int|string,
     *     name: string,
     *     email: string,
     *     roles?: list<array{name?: string}|string>,
     *     email_verified_at?: mixed,
     *     is_in_lobby?: bool,
     *     has_operational_units?: bool,
     *     active_unit?: array{id: int|string, slug: string, name: string},
     *     operational_units?: list<array{id: int|string, slug: string, name: string}>,
     *     units_with_roles?: array<string, list<string>>
     * }|null
     */
    public function present(array $user, ?UsimUnit $activeUnit = null): ?array
    {
        $id = $user['id'] ?? null;
        $name = $user['name'] ?? null;
        $email = $user['email'] ?? null;

        if ((!is_int($id) && !is_string($id)) || !is_string($name) || !is_string($email)) {
            return null;
        }

        $modalUser = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ];

        if (array_key_exists('email_verified_at', $user)) {
            $modalUser['email_verified_at'] = $user['email_verified_at'];
        }

        foreach (['is_in_lobby', 'has_operational_units'] as $key) {
            if (isset($user[$key]) && is_bool($user[$key])) {
                $modalUser[$key] = $user[$key];
            }
        }

        if (isset($user['roles']) && is_array($user['roles'])) {
            $roles = [];
            foreach ($user['roles'] as $role) {
                if (is_string($role)) {
                    $roles[] = $role;
                } elseif (is_array($role) && isset($role['name']) && is_string($role['name'])) {
                    $roles[] = ['name' => $role['name']];
                }
            }
            $modalUser['roles'] = $roles;
        }

        if (isset($user['operational_units']) && is_array($user['operational_units'])) {
            $operationalUnits = [];
            foreach ($user['operational_units'] as $unit) {
                if (!is_array($unit)) {
                    continue;
                }

                $unitId = $unit['id'] ?? null;
                $unitSlug = $unit['slug'] ?? null;
                $unitName = $unit['name'] ?? null;
                if ((is_int($unitId) || is_string($unitId)) && is_string($unitSlug) && is_string($unitName)) {
                    $operationalUnits[] = ['id' => $unitId, 'slug' => $unitSlug, 'name' => $unitName];
                }
            }
            $modalUser['operational_units'] = $operationalUnits;
        }

        if (isset($user['units_with_roles']) && is_array($user['units_with_roles'])) {
            $unitsWithRoles = [];
            foreach ($user['units_with_roles'] as $slug => $unitRoles) {
                if (!is_string($slug) || !is_array($unitRoles)) {
                    continue;
                }

                $unitsWithRoles[$slug] = array_values(array_filter($unitRoles, 'is_string'));
            }
            $modalUser['units_with_roles'] = $unitsWithRoles;
        }

        if ($activeUnit) {
            $modalUser['active_unit'] = [
                'id' => $activeUnit->id,
                'slug' => $activeUnit->slug,
                'name' => ($activeUnit->display_name !== $activeUnit->translation_key)
                    ? $activeUnit->display_name
                    : ucfirst($activeUnit->slug),
            ];
        }

        return $modalUser;
    }
}
