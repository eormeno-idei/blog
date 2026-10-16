<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Admin\Concerns;

use App\Services\Units\UnitContextResolver;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Trait to resolve active organizational unit context for screens.
 */
trait ResolvesActiveUnitContext
{
    /**
     * Resolves the active organizational unit for the current user and context.
     */
    protected function resolveActiveUnit(): ?UsimUnit
    {
        /** @var Authenticatable|null $user */
        $user = Auth::user();

        // 1. Single source of truth from UIStateManager cache
        $activeSlug = UIStateManager::getActiveUnit();
        if ($activeSlug !== null) {
            $unit = UnitContextResolver::resolve($user, $activeSlug);
            if ($unit) {
                return $unit;
            }
        }

        // 2. Ambient permissions team ID (active Spatie team context)
        if (function_exists('getPermissionsTeamId') && getPermissionsTeamId()) {
            $unit = UsimUnit::find(getPermissionsTeamId());
            if ($unit) {
                return $unit;
            }
        }

        return UnitContextResolver::resolve($user, null)
            ?? UsimUnit::where('slug', 'main')->first()
            ?? UsimUnit::where('slug', 'lobby')->first();
    }
}
