<?php

// @usim: feature="admin", type="service"

namespace App\Services\Units;

use App\Models\User;
use Idei\Usim\Contracts\UnitContextResolverInterface;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resolves and applies the active organizational unit (permissions team) for a request.
 *
 * The requested slug is only honored when it actually belongs to the user. If it is
 * missing, invalid, or not one of the user's units, the user's first assigned unit
 * (deterministically ordered) is used as a fallback.
 */
class UnitContextResolver implements UnitContextResolverInterface
{
    public function resolveUserUnit(?Authenticatable $user, ?string $slug): ?UsimUnit
    {
        return self::resolve($user, $slug);
    }

    public function applyUserUnit(?Authenticatable $user, ?string $slug): ?UsimUnit
    {
        return self::resolveAndApply($user, $slug);
    }

    /**
     * Resolve the unit context for the given user and apply it as the current
     * permissions team (via setPermissionsTeamId), returning the resolved unit.
     */
    public static function resolveAndApply(?Authenticatable $user, ?string $slug): ?UsimUnit
    {
        $unit = self::resolve($user, $slug);

        if ($unit) {
            setPermissionsTeamId($unit->id);
        }

        return $unit;
    }

    /**
     * Resolve the unit the given user should operate in, without applying it.
     *
     * Expects the user model to expose a `usimUnits()` BelongsToMany relation.
     */
    public static function resolve(?Authenticatable $user, ?string $slug): ?UsimUnit
    {
        if (! $user) {
            return null;
        }

        $isRoot = method_exists($user, 'isRoot') && $user->isRoot();

        // Scoped to the user's own units (unless root) so an
        // untrusted client-supplied slug can't grant access to another unit.
        if (\is_string($slug) && $slug !== '') {
            if ($isRoot) {
                $unit = UsimUnit::where('slug', $slug)->first();
                if ($unit) {
                    return $unit;
                }
            }
            if (method_exists($user, 'usimUnits')) {
                $unit = $user->usimUnits()->where('slug', $slug)->first();
                if ($unit) {
                    return $unit;
                }
            }
        }

        // Check if an active unit is already chosen for the current client in UIStateManager
        $clientActiveUnit = UIStateManager::getActiveUnit();
        if (\is_string($clientActiveUnit) && $clientActiveUnit !== '') {
            if ($isRoot) {
                $unit = UsimUnit::where('slug', $clientActiveUnit)->first();
                if ($unit) {
                    return $unit;
                }
            }
            if (method_exists($user, 'usimUnits')) {
                $unit = $user->usimUnits()->where('slug', $clientActiveUnit)->first();
                if ($unit) {
                    return $unit;
                }
            }
        }

        // Check if an existing permissions team ID is set and belongs to the user
        if (function_exists('getPermissionsTeamId') && getPermissionsTeamId()) {
            $teamId = (int) getPermissionsTeamId();
            if ($isRoot) {
                $unit = UsimUnit::find($teamId);
                if ($unit) {
                    return $unit;
                }
            }
            if (method_exists($user, 'usimUnits')) {
                $unit = $user->usimUnits()->where('usim_units.id', $teamId)->first();
                if ($unit) {
                    return $unit;
                }
            }
        }

        // Requested slug is missing or doesn't belong to the user: fall back to the
        // first operational unit available, deterministically ordered by id.
        if ($isRoot) {
            $firstOperational = UsimUnit::where(function ($q) {
                $q->where('type', '!=', 'system')->orWhereNull('type');
            })->orderBy('id')->first();
            if ($firstOperational) {
                return $firstOperational;
            }
        }

        if (method_exists($user, 'usimUnits')) {
            $firstOperational = $user->usimUnits()->where(function ($q) {
                $q->where('type', '!=', 'system')->orWhereNull('type');
            })->orderBy('usim_units.id')->first();
            if ($firstOperational) {
                return $firstOperational;
            }

            $firstUnit = $user->usimUnits()->orderBy('usim_units.id')->first();
            if ($firstUnit) {
                return $firstUnit;
            }
        }

        if ($isRoot) {
            return UsimUnit::where('slug', 'main')->first()
                ?? UsimUnit::where('slug', 'lobby')->first()
                ?? UsimUnit::orderBy('id')->first();
        }

        return null;
    }
}
