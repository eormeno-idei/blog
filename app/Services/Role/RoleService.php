<?php
// @usim: feature="admin", type="service"
namespace App\Services\Role;

use Idei\Usim\Models\UsimRole;
use Spatie\Permission\Models\Permission;

class RoleService
{
    protected RoleListingService $roleListingService;
    public function __construct(
        ?RoleListingService $roleListingService = null
    ) {
        $this->roleListingService = $roleListingService ?? app(RoleListingService::class);
    }

    /**
     * Return all allowed roles.
     *
     * @param bool $excludeSystemRoles Whether to exclude the 'root', 'guest' and 'default registering' roles
     * @param array<string> $excludedGuards A list of excluded guards
     * @return list<UsimRole>
     */
    public function getAllowedRoles(
        bool $excludeSystemRoles = true,
        array $excludedGuards = []
    ): array {
        $systemRoles = ['root', 'guest', config('usim.default_registering_role')];
        $roles = $this->roleListingService->sortBy('priority');

        if (!empty($excludedGuards)) {
            $roles = array_values(array_filter(
                $roles,
                static fn(UsimRole $role): bool => !\in_array($role->guard_name, $excludedGuards, true)
            ));
        }

        if ($excludeSystemRoles) {
            $roles = array_values(array_filter(
                $roles,
                static fn(UsimRole $role): bool => !\in_array($role->name, $systemRoles, true)
            ));
        }

        return array_values($roles);
    }

    /**
     * Devuelve los roles permitidos para una clase de actor específica ('web' para User, 'device' para Device).
     *
     * @param string $actorClass FQCN del modelo (ej. User::class o Device::class)
     * @param bool $excludeSystemRoles
     * @return list<UsimRole>
     */
    public function getRolesForActor(string $actorClass, bool $excludeSystemRoles = true): array
    {
        $targetGuard = match ($actorClass) {
            \App\Models\Device::class => 'device',
            default => 'web',
        };

        $roles = $this->roleListingService->sortBy('priority');

        $filtered = array_filter(
            $roles,
            static fn(UsimRole $role): bool => $role->guard_name === $targetGuard
        );

        if ($excludeSystemRoles) {
            /** @var \Idei\Usim\Support\UsimConfig $usimConfig */
            $usimConfig = app(\Idei\Usim\Support\UsimConfig::class);
            $systemRoles = ['root', 'guest', $usimConfig->defaultRegisteringRole];

            $filtered = array_filter(
                $filtered,
                static fn(UsimRole $role): bool => !\in_array($role->name, $systemRoles, true)
            );
        }

        return array_values($filtered);
    }

    /**
     * Return all permission IDs associated with a role.
     *
     * @param UsimRole|int|string $role
     * @return list<int|string>
     */
    public function getPermissionIds(UsimRole|int|string $role): array
    {
        $roleModel = $this->findRole($role);

        if (!$roleModel instanceof UsimRole) {
            return [];
        }

        if ($roleModel->relationLoaded('permissions')) {
            /** @var list<int|string> $ids */
            $ids = $roleModel->permissions
                ->pluck('id')
                ->map(static fn(mixed $id): int|string => is_numeric($id) ? (int) $id : (is_string($id) ? $id : ''))
                ->values()
                ->toArray();

            return $ids;
        }

        /** @var list<int|string> $ids */
        $ids = $roleModel->permissions()
            ->pluck('permissions.id')
            ->map(static fn(mixed $id): int|string => is_numeric($id) ? (int) $id : (is_string($id) ? $id : ''))
            ->values()
            ->toArray();

        return $ids;
    }

    /**
     * Toggle a permission for a role (attach if absent, detach if present).
     *
     * @param UsimRole|int|string $role Role instance, ID, or name
     * @param Permission|int|string $permission Permission instance, ID, or name
     * @return bool True if permission is now attached, false if detached or not found
     */
    public function togglePermission(UsimRole|int|string $role, Permission|int|string $permission): bool
    {
        $roleModel = $this->findRole($role);
        $permissionModel = $this->findPermission($permission);

        if (!$roleModel instanceof UsimRole || !$permissionModel instanceof Permission) {
            return false;
        }

        $hasPermission = $roleModel->permissions()->where('permissions.id', $permissionModel->id)->exists();

        if ($hasPermission) {
            $roleModel->revokePermissionTo($permissionModel);
            $roleModel->unsetRelation('permissions');

            return false;
        }

        $roleModel->givePermissionTo($permissionModel);
        $roleModel->unsetRelation('permissions');

        return true;
    }

    /**
     * Add a permission to a role.
     *
     * @param UsimRole|int|string $role Role instance, ID, or name
     * @param Permission|int|string $permission Permission instance, ID, or name
     * @return bool True if successful, false if role or permission not found
     */
    public function addPermission(UsimRole|int|string $role, Permission|int|string $permission): bool
    {
        $roleModel = $this->findRole($role);
        $permissionModel = $this->findPermission($permission);

        if (!$roleModel instanceof UsimRole || !$permissionModel instanceof Permission) {
            return false;
        }

        $roleModel->givePermissionTo($permissionModel);
        $roleModel->unsetRelation('permissions');

        return true;
    }

    /**
     * Remove a permission from a role.
     *
     * @param UsimRole|int|string $role Role instance, ID, or name
     * @param Permission|int|string $permission Permission instance, ID, or name
     * @return bool True if successful, false if role or permission not found
     */
    public function removePermission(UsimRole|int|string $role, Permission|int|string $permission): bool
    {
        $roleModel = $this->findRole($role);
        $permissionModel = $this->findPermission($permission);

        if (!$roleModel instanceof UsimRole || !$permissionModel instanceof Permission) {
            return false;
        }

        $roleModel->revokePermissionTo($permissionModel);
        $roleModel->unsetRelation('permissions');

        return true;
    }

    /**
     * Find a role instance by model, ID, or name.
     *
     * @param UsimRole|int|string $role
     * @return UsimRole|null
     */
    public function findRole(UsimRole|int|string $role): ?UsimRole
    {
        if ($role instanceof UsimRole) {
            return $role;
        }

        /** @var UsimRole|null $found */
        $found = UsimRole::find($role) ?? UsimRole::query()->where('name', (string) $role)->first();

        return $found;
    }

    /**
     * Find a permission instance by model, ID, or name.
     *
     * @param Permission|int|string $permission
     * @return Permission|null
     */
    public function findPermission(Permission|int|string $permission): ?Permission
    {
        if ($permission instanceof Permission) {
            return $permission;
        }

        /** @var Permission|null $found */
        $found = Permission::find($permission) ?? Permission::query()->where('name', (string) $permission)->first();

        return $found;
    }
}
