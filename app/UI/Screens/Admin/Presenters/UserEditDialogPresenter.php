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
     * @param  array<string, mixed>  $user
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

        if (! $this->hasValidIdentity($id, $name, $email)) {
            return null;
        }

        /** @var int|string $id */
        /** @var string $name */
        /** @var string $email */
        $modalUser = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ];

        $this->applyOptionalFlags($modalUser, $user);

        if (isset($user['roles']) && is_array($user['roles'])) {
            $modalUser['roles'] = $this->normalizeRoles($user['roles']);
        }

        if (isset($user['operational_units']) && is_array($user['operational_units'])) {
            $modalUser['operational_units'] = $this->normalizeOperationalUnits($user['operational_units']);
        }

        if (isset($user['units_with_roles']) && is_array($user['units_with_roles'])) {
            $modalUser['units_with_roles'] = $this->normalizeUnitsWithRoles($user['units_with_roles']);
        }

        if ($activeUnit !== null) {
            $modalUser['active_unit'] = $this->formatActiveUnit($activeUnit);
        }

        return $modalUser;
    }

    /**
     * Check if the user payload has valid required identity fields.
     */
    private function hasValidIdentity(mixed $id, mixed $name, mixed $email): bool
    {
        return (is_int($id) || is_string($id)) && is_string($name) && is_string($email);
    }

    /**
     * Copy optional verification and unit status flags when present.
     *
     * @param  array<string, mixed>  $modalUser
     * @param  array<string, mixed>  $user
     */
    private function applyOptionalFlags(array &$modalUser, array $user): void
    {
        if (array_key_exists('email_verified_at', $user)) {
            $modalUser['email_verified_at'] = $user['email_verified_at'];
        }

        foreach (['is_in_lobby', 'has_operational_units'] as $key) {
            if (isset($user[$key]) && is_bool($user[$key])) {
                $modalUser[$key] = $user[$key];
            }
        }
    }

    /**
     * Normalize role entries supporting string names and associative arrays.
     *
     * @param  array<mixed>  $rawRoles
     * @return list<array{name: string}|string>
     */
    private function normalizeRoles(array $rawRoles): array
    {
        $roles = [];

        foreach ($rawRoles as $role) {
            if (is_string($role)) {
                $roles[] = $role;
            } elseif (is_array($role) && isset($role['name']) && is_string($role['name'])) {
                $roles[] = ['name' => $role['name']];
            }
        }

        return $roles;
    }

    /**
     * Normalize operational units list.
     *
     * @param  array<mixed>  $rawUnits
     * @return list<array{id: int|string, slug: string, name: string}>
     */
    private function normalizeOperationalUnits(array $rawUnits): array
    {
        $operationalUnits = [];

        foreach ($rawUnits as $unit) {
            if (is_array($unit) && $this->isValidUnitEntry($unit)) {
                /** @var array{id: int|string, slug: string, name: string} $unit */
                $operationalUnits[] = [
                    'id' => $unit['id'],
                    'slug' => $unit['slug'],
                    'name' => $unit['name'],
                ];
            }
        }

        return $operationalUnits;
    }

    /**
     * Check whether an operational unit array entry contains valid id, slug, and name.
     *
     * @param  array<mixed>  $unit
     */
    private function isValidUnitEntry(array $unit): bool
    {
        $unitId = $unit['id'] ?? null;
        $unitSlug = $unit['slug'] ?? null;
        $unitName = $unit['name'] ?? null;

        return (is_int($unitId) || is_string($unitId)) && is_string($unitSlug) && is_string($unitName);
    }

    /**
     * Normalize map of unit slugs to role name lists.
     *
     * @param  array<mixed>  $rawUnitsWithRoles
     * @return array<string, list<string>>
     */
    private function normalizeUnitsWithRoles(array $rawUnitsWithRoles): array
    {
        $unitsWithRoles = [];

        foreach ($rawUnitsWithRoles as $slug => $unitRoles) {
            if (is_string($slug) && is_array($unitRoles)) {
                $unitsWithRoles[$slug] = array_values(array_filter($unitRoles, 'is_string'));
            }
        }

        return $unitsWithRoles;
    }

    /**
     * Format the active UsimUnit model for modal display.
     *
     * @return array{id: int|string, slug: string, name: string}
     */
    private function formatActiveUnit(UsimUnit $activeUnit): array
    {
        return [
            'id' => $activeUnit->id,
            'slug' => $activeUnit->slug,
            'name' => ($activeUnit->display_name !== $activeUnit->translation_key)
                ? $activeUnit->display_name
                : ucfirst($activeUnit->slug),
        ];
    }
}
