<?php
// @usim: feature="admin", type="service"
namespace App\Services\User;

use App\Models\User;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

class UsimUserService
{
    /**
     * Crea o actualiza un usuario basándose en la key de config/usim.php
     * y le asigna las membresías y roles correspondientes (unit_roles).
     */
    public function provisionFromConfig(string $configKey): User
    {
        /** @var array<string, mixed> $userData */
        $userData = config("usim.users.{$configKey}");

        if (!$userData) {
            throw new InvalidArgumentException("La key '{$configKey}' no existe en la configuración usim.users.");
        }

        /** @var string $firstName */
        $firstName = $userData['first_name'] ?? '';
        /** @var string $lastName */
        $lastName = $userData['last_name'] ?? '';
        /** @var string $name */
        $name = trim("{$firstName} {$lastName}");
        /** @var string $password */
        $password = $userData['password'] ?? 'password';

        $user = User::updateOrCreate(
            ['email' => $userData['email']],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(), // Útil para que los tests no reboten en middleware de verificación
            ]
        );

        /** @var array<string, array<string>> $unitRoles */
        $unitRoles = $userData['unit_roles'];
        /** @var array<int, int> $unitIdsForMembership */
        $unitIdsForMembership = [];

        if ($unitRoles) {
            foreach ($unitRoles as $unitSlug => $roles) {
                // En un entorno de test, si la unidad no existe, la creamos al vuelo.
                $unitType = config("usim.units.structure.{$unitSlug}.type");
                $unit = UsimUnit::firstOrCreate(
                    ['slug' => $unitSlug],
                    ['type' => is_string($unitType) ? $unitType : null]
                );
                if (empty($unit->type) && is_string($unitType)) {
                    $unit->update(['type' => $unitType]);
                }
                $unitIdsForMembership[] = $unit->id;

                app(PermissionRegistrar::class)->setPermissionsTeamId($unit->id);

                // Aseguramos que los roles existan antes de asignarlos
                foreach ($roles as $roleName) {
                    \Spatie\Permission\Models\Role::findOrCreate($roleName);
                }

                $user->syncRoles($roles);
            }

            // Sincronizar membresía física
            $user->usimUnits()->sync($unitIdsForMembership);
        }

        // Limpiar caché de Spatie tras mutar permisos (esencial para los tests)
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
