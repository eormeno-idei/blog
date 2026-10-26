<?php

// @usim: feature="admin", type="service"

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Units\UnitContextResolver;
use App\UI\Screens\Home;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Support\Facades\Auth;

class AuthSessionService
{
    public function issueToken(User $user, bool $remember = false): string
    {
        $tokenName = $remember ? 'auth_token_remember' : 'auth_token';

        return $remember
            ? $user->createToken($tokenName, ['*'], now()->addDays(30))->plainTextToken
            : $user->createToken($tokenName, ['*'], now()->addDay())->plainTextToken;
    }

    public function establishSession(
        User $user,
        ?string $unit = null,
        ?string $token = null,
        ?string $homeScreen = null
    ): string {
        // Support legacy callers passing ($user, $token) where $unit contains the auth token
        if ($token === null && \is_string($unit)) {
            if (str_contains($unit, '|') || str_contains($unit, '.') || strlen($unit) > 30) {
                $token = $unit;
                $unit = null;
            }
        }

        if (\is_string($token) && $token !== '') {
            UIStateManager::setAuthToken($token);
        }

        Auth::login($user);

        $activeUnitSlug = $unit;

        if (config('permission.teams', false)) {
            $resolvedUnit = UnitContextResolver::resolveAndApply($user, $unit);
            if ($resolvedUnit) {
                $activeUnitSlug = $resolvedUnit->slug;
            } elseif (\is_string($unit) && $unit !== '') {
                $fallbackUnit = UsimUnit::where('slug', $unit)->first();
                if ($fallbackUnit) {
                    setPermissionsTeamId($fallbackUnit->id);
                    $activeUnitSlug = $fallbackUnit->slug;
                }
            }

            if (getPermissionsTeamId() === null) {
                $defaultUnit = UsimUnit::where('slug', 'main')->first()
                    ?? UsimUnit::where('slug', 'lobby')->first()
                    ?? UsimUnit::orderBy('id')->first();
                if ($defaultUnit) {
                    setPermissionsTeamId($defaultUnit->id);
                    $activeUnitSlug = $activeUnitSlug ?? $defaultUnit->slug;
                }
            }
        }

        /** @var class-string<Screen> $resolvedHomeScreen */
        $resolvedHomeScreen = $homeScreen && class_exists($homeScreen) && is_subclass_of($homeScreen, Screen::class)
            ? $homeScreen
            : $this->resolveHomeScreen($user, $activeUnitSlug);

        event(new UsimEvent('logged_user', [
            'user' => $user,
            'timestamp' => now(),
            'unit' => $activeUnitSlug ?? 'lobby',
            'home_screen' => $resolvedHomeScreen,
            'token' => $token,
            'message' => t('service.auth.login.success'),
        ]));

        return $resolvedHomeScreen::getRoutePath();
    }

    /**
     * Terminate the user session, revoke auth tokens, and dispatch domain logout event.
     *
     * @return class-string<Screen>
     */
    public function logout(?User $user = null): string
    {
        /** @var User|null $currentUser */
        $currentUser = $user ?? (Auth::user() instanceof User ? Auth::user() : null);

        if ($currentUser instanceof User) {
            $currentUser->tokens()->where('name', 'like', 'auth_token%')->delete();
        }

        Auth::guard('web')->logout();
        UIStateManager::setAuthToken(null);
        UIStateManager::setActiveUnit(null);

        if (function_exists('setPermissionsTeamId') && config('permission.teams')) {
            setPermissionsTeamId(null);
        }

        /** @var class-string<Screen> $homeScreen */
        $homeScreen = Home::class;

        event(new UsimEvent('user_logged_out', [
            'user' => $currentUser,
            'home_screen' => $homeScreen,
        ]));

        return $homeScreen;
    }

    /**
     * Alias for establishSession to maintain backwards compatibility.
     *
     * @deprecated Use establishSession instead.
     */
    public function start(
        User $user,
        ?string $unit = null,
        ?string $token = null,
        ?string $homeScreen = null
    ): string {
        return $this->establishSession($user, $unit, $token, $homeScreen);
    }

    /**
     * Resolve the home screen for a user based on their roles and unit context.
     *
     * @return class-string<Screen>
     */
    public function resolveHomeScreen(User $user, ?string $unit = null): string
    {
        if (config('permission.teams', false)) {
            if ($unit !== null || getPermissionsTeamId() === null) {
                $resolvedUnit = UnitContextResolver::resolveAndApply($user, $unit);
                if (! $resolvedUnit && is_string($unit) && $unit !== '') {
                    $fallbackUnit = UsimUnit::where('slug', $unit)->first();
                    if ($fallbackUnit) {
                        setPermissionsTeamId($fallbackUnit->id);
                    }
                }
                $user->unsetRelation('roles');
            }
        }

        $roleNames = $user->getRoleNames()->values()->toArray();

        if ($user->isRoot() && ! in_array('root', $roleNames, true)) {
            $roleNames[] = 'root';
        }

        if (empty($roleNames)) {
            $roleNames = $user->globalRoles()->pluck('name')->toArray();
        }

        $roleNames = array_values(array_unique(array_filter($roleNames, 'is_string')));

        // 1. Obtenemos los modelos de roles desde la base de datos junto con sus configuraciones
        $roles = UsimRole::with('usimSetting')
            ->whereIn('name', $roleNames)
            ->get();

        // 2. Ordenamos por la prioridad definida en la base de datos (usim_role_settings)
        $sortedRoles = $roles->sortBy(function ($role) {
            return $role->usimSetting ? $role->usimSetting->priority : 100;
        });

        // 3. Buscamos la primera pantalla de inicio válida según la prioridad
        foreach ($sortedRoles as $role) {
            $screenClass = $role->usimSetting ? $role->usimSetting->home_screen : null;

            if (
                \is_string($screenClass)
                && $screenClass !== ''
                && class_exists($screenClass)
                && is_subclass_of($screenClass, Screen::class)
            ) {
                return $screenClass;
            }
        }

        return Home::class;
    }

    public function resolvePostLoginRedirect(User $user, ?string $unit = null): string
    {
        return $this->resolveHomeScreen($user, $unit)::getRoutePath();
    }
}
