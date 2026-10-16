<?php

// @usim: feature="admin", type="service"

namespace App\Services\Units;

use App\Models\User;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UsimUnitsService
{
    /**
     * Determines whether there are any operational units (departments, institutes, etc.)
     * defined in the system beyond the standard system units ('main' and 'lobby').
     */
    public function hasOperationalUnits(): bool
    {
        return UsimUnit::query()
            ->where(function ($q) {
                $q->where('type', '!=', 'system')->orWhereNull('type');
            })
            ->whereNotIn('slug', ['main', 'lobby'])
            ->exists();
    }

    /**
     * Get the available units for the given user.
     * If the user is a root user, return all units; otherwise,
     *  return only the units associated with the user.
     *
     * @return Collection<int, UsimUnit>
     */
    public function getAvailableUnits(): Collection
    {
        if (! Auth::check()) {
            return collect();
        }

        /** @var User $user */
        $user = Auth::user();
        if ($user->isRoot()) {
            return UsimUnit::query()->where(function ($q) {
                $q->where('type', '!=', 'system')->orWhereNull('type');
            })->get();
        }

        return $user->usimUnits()->where(function ($q) {
            $q->where('type', '!=', 'system')->orWhereNull('type');
        })->get();
    }

    /**
     * Given a user, returns the units they have access to, and for each unit, the roles that the user has.
     * As a result:
     * [
     *   'unit_slug' => ['role1', 'role2'],
     *   'unit_slug2' => ['role3'],
     * ]
     *
     * @return array<string, array<mixed>>
     */
    public function getUserUnitsWithRoles(User $user): array
    {
        $unitsWithRoles = [];

        $units = $user->usimUnits;
        foreach ($units as $unit) {
            $unitsWithRoles[$unit->slug] = [];
        }

        if (! config('permission.teams', false)) {
            $roles = $user->getRoleNames()->values()->toArray();
            foreach (array_keys($unitsWithRoles) as $slug) {
                $unitsWithRoles[$slug] = $roles;
            }

            return $unitsWithRoles;
        }

        /** @var string $teamForeignKey */
        $teamForeignKey = config('permission.column_names.team_foreign_key') ?? 'usim_unit_id';
        /** @var class-string $modelHasRolesTable */
        $modelHasRolesTable = config('permission.table_names.model_has_roles') ?? 'model_has_roles';
        /** @var class-string $rolesTable */
        $rolesTable = config('permission.table_names.roles') ?? 'roles';
        /** @var string $modelMorphKey */
        $modelMorphKey = config('permission.column_names.model_morph_key') ?? 'model_id';

        $assignedRoles = DB::table($modelHasRolesTable)
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$modelHasRolesTable}.role_id")
            ->join('usim_units', 'usim_units.id', '=', "{$modelHasRolesTable}.{$teamForeignKey}")
            ->where("{$modelHasRolesTable}.model_type", $user->getMorphClass())
            ->where("{$modelHasRolesTable}.{$modelMorphKey}", $user->getKey())
            ->select('usim_units.slug', "{$rolesTable}.name as role_name")
            ->get();

        foreach ($assignedRoles as $row) {
            if (! isset($unitsWithRoles[$row->slug])) {
                $unitsWithRoles[$row->slug] = [];
            }

            if (! in_array($row->role_name, $unitsWithRoles[$row->slug], true)) {
                $unitsWithRoles[$row->slug][] = $row->role_name;
            }
        }

        return $unitsWithRoles;
    }
}
