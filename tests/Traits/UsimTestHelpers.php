<?php
// @usim: feature="core", type="test"
namespace Tests\Traits;

use App\Models\User;
use App\Services\User\UsimUserService;
use App\UI\Screens\Auth\Login;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Screen;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * @mixin \Tests\TestCase
 */
trait UsimTestHelpers
{
    // use RefreshDatabase;

    /**
     * Logs in a configured role user via the real UI event endpoint.
     *
     * @param string $role
     * @param class-string<Screen>|null $withScreenPermission
     * @return array{user: User, response: TestResponse, config: array<string, mixed>}
     */
    public function loginAs(string $role, ?string $withScreenPermission = null): array
    {
        $allowedRoles = array_keys(config('usim.roles', []));
        if (!\in_array($role, $allowedRoles, true)) {
            throw new InvalidArgumentException("Unsupported role '{$role}'. Expected one of: " . implode(', ', $allowedRoles) . ".");
        }

        $roleModel = Role::findOrCreate($role);

        $permissions = config("usim.roles.{$role}.permissions", []);
        if ($withScreenPermission !== null) {
            $screenPermission = array_keys($withScreenPermission::resolvedPermissions());
            $permissions = \array_merge($permissions, $screenPermission);
        }
        foreach ($permissions as $permName) {
            $permission = Permission::findOrCreate($permName);
            $roleModel->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $unit = UsimUnit::firstOrCreate(['slug' => 'main']);
        setPermissionsTeamId($unit->id);

        $userConfig = config("usim.users.roles.{$role}.seed_user", config("users.roles.{$role}.seed_user", []));
        $firstName = $userConfig['first_name'] ?? ucfirst($role);
        $lastName = $userConfig['last_name'] ?? 'User';
        $email = $userConfig['email'] ?? "{$role}@example.com";
        $password = $userConfig['password'] ?? 'password';

        $user = User::factory()->create([
            'name' => trim("{$firstName} {$lastName}"),
            'email' => $email,
            'password' => bcrypt($password),
        ]);
        $user->assignRole($role);

        /** @var TestResponse $uiResponse */
        $uiResponse = getScreenJson($this, Login::class);
        $uiResponse->assertOk();
        $componentId = serviceRootComponentId($uiResponse->json());

        $response = $this->postJson('/api/ui-event', [
            'component_id' => $componentId,
            'event' => 'click',
            'action' => 'submit_login',
            'parameters' => [
                'login_email' => $email,
                'login_password' => $password,
            ],
        ]);

        return [
            'user' => $user,
            'response' => $response,
            'config' => $userConfig,
        ];
    }

    /**
     * Creates the configured root user along with the
     * role and permissions.
     *
     * @return User
     */
    public function createRootUser(): User
    {
        $role = Role::findOrCreate('root');
        $permissions = config("usim.roles.root.permissions", []);
        foreach ($permissions as $permName) {
            $permission = Permission::findOrCreate($permName);
            $role->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $unit = UsimUnit::firstOrCreate(['slug' => 'main']);
        setPermissionsTeamId($unit->id);

        $userConfig = config("usim.users.roles.root.seed_user", []);
        $firstName = $userConfig['first_name'] ?? 'Root';
        $lastName = $userConfig['last_name'] ?? 'User';
        $email = $userConfig['email'] ?? "root@example.com";
        $password = $userConfig['password'] ?? 'password';

        $user = User::factory()->create([
            'name' => trim("{$firstName} {$lastName}"),
            'email' => $email,
            'password' => bcrypt($password),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('root');
        return $user;
    }

    /**
     * Creates the default registering role along with the role's
     * permissions.
     *
     * @return User
     */
    public function createDefaultUser(): User
    {
        $defaultRole = config('usim.default_registering_role', 'user');
        $role = Role::findOrCreate($defaultRole);
        $permissions = config("usim.roles.{$defaultRole}.permissions", []);
        foreach ($permissions as $permName) {
            $permission = Permission::findOrCreate($permName);
            $role->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $unit = UsimUnit::firstOrCreate(['slug' => 'main']);
        setPermissionsTeamId($unit->id);

        $firstName = ucfirst('Default');
        $lastName = 'User';
        $email = "default@example.com";
        $password = 'password';

        $user = User::factory()->create([
            'name' => trim("{$firstName} {$lastName}"),
            'email' => $email,
            'password' => bcrypt($password),
            'email_verified_at' => null,
        ]);
        $user->assignRole($defaultRole);
        return $user;
    }

    /**
     * Aprovisiona un usuario desde la configuración y lo autentica en el test.
     *
     * @param string $configKey La key del usuario en config('usim.users') (ej: 'root', 'admin')
     * @param bool $viaUi Si es true, simula el request JSON a /api/ui-event en lugar de usar actingAs()
     * @return array{user: User, response?: TestResponse, config: array<string, mixed>}
     */
    public function loginAsConfiguredUser(string $configKey, bool $viaUi = false): array
    {
        /** @var UsimUserService $userService */
        $userService = app(UsimUserService::class);

        // El servicio centraliza la creación y asignación de unit_roles
        $user = $userService->provisionFromConfig($configKey);
        $userConfig = config("usim.users.{$configKey}");

        // 1. Logueo rápido a nivel backend (ideal para tests de modelos, policies o endpoints directos)
        if (!$viaUi) {
            $this->actingAs($user);

            return [
                'user' => $user,
                'config' => $userConfig,
            ];
        }

        // 2. Logueo completo simulando el Server-Driven UI
        // Obtenemos el componente inicial como en tu implementación original
        /** @var UsimUserService $userService */
        $userService = app(UsimUserService::class);

        /** @var TestResponse $uiResponse */
        $uiResponse = getScreenJson($this, Login::class);
        $uiResponse->assertOk();

        $componentId = serviceRootComponentId($uiResponse->json());

        // Simulamos el evento click sobre el botón submit_login
        $response = $this->postJson('/api/ui-event', [
            'component_id' => $componentId,
            'event' => 'click',
            'action' => 'submit_login',
            'parameters' => [
                'login_email' => $userConfig['email'],
                'login_password' => $userConfig['password'],
            ],
        ]);

        return [
            'user' => $user,
            'response' => $response,
            'config' => $userConfig,
        ];
    }
}
