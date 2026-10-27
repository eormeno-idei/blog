<?php

// @usim: feature="admin", type="service"

namespace App\Services\User;

use App\Models\User;
use App\Services\Units\UsimUnitsService;
use Idei\Usim\Contracts\UserMutationServiceInterface;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Spatie\Permission\PermissionRegistrar;

class UserService implements UserMutationServiceInterface
{
    /**
     * Find user by ID
     */
    public function findUser(int $userId): ?User
    {
        return User::find($userId);
    }

    /**
     * Get user with roles
     *
     * @return array{status: 'success', message: string, data: array<string, mixed>} | array{status: 'error', message: string, errors: array<string, string[]>}
     */
    public function getUser(int $userId): array
    {
        $user = User::find($userId);

        if (! $user) {
            return [
                'status' => 'error',
                'message' => 'Usuario no encontrado',
                'errors' => ['user_id' => ['The user does not exist.']],
            ];
        }

        $user->load('roles');
        $user->load('globalRoles');
        $user->load('usimUnits');

        $userData = $user->toArray();
        if (empty($userData['roles']) && ! empty($userData['global_roles'])) {
            $userData['roles'] = $userData['global_roles'];
        }

        /** @var UsimUnitsService $unitsService */
        $unitsService = app(UsimUnitsService::class);
        $hasOperationalUnits = $unitsService->hasOperationalUnits();

        $isInLobby = $user->isRegisteredInLobby();

        $operationalUnits = [];
        if ($hasOperationalUnits) {
            $operationalUnits = UsimUnit::query()
                ->where(function ($q) {
                    $q->where('type', '!=', 'system')->orWhereNull('type');
                })
                ->whereNotIn('slug', ['main', 'lobby'])
                ->get()
                ->map(static fn(UsimUnit $u): array => [
                    'id' => $u->id,
                    'slug' => $u->slug,
                    'name' => ($u->display_name !== $u->translation_key) ? $u->display_name : ucfirst($u->slug),
                ])
                ->values()
                ->toArray();
        }

        $userData['is_in_lobby'] = $isInLobby;
        $userData['has_operational_units'] = $hasOperationalUnits;
        $userData['operational_units'] = $operationalUnits;
        $userData['units_with_roles'] = $unitsService->getUserUnitsWithRoles($user);

        return [
            'status' => 'success',
            'message' => "Usuario $user->name recuperado exitosamente",
            'data' => $userData,
        ];
    }

    /**
     * Update user with validation and role syncing
     *
     * @param  array<string, mixed>  $data
     * @return array{status: 'success', message: string, data: array<string, mixed>} | array{status: 'error', message: string, errors: array<string, string[]>}
     */
    public function updateUser(Model $user, array $data): array
    {
        assert($user instanceof User);
        $error = $this->validateUpdateData($data);
        if ($error) {
            return $error;
        }

        $updateDataResult = $this->buildUpdateData($user, $data);
        if (isset($updateDataResult['status']) && $updateDataResult['status'] === 'error') {
            /** @var array{status: 'error', message: string, errors: array<string, string[]>} $updateDataResult */
            return $updateDataResult;
        }

        if (! empty($updateDataResult)) {
            $user->update($updateDataResult);
        }

        if (array_key_exists('roles', $data)) {
            /** @var array<int, string> $roles */
            $roles = $data['roles'];
            $rolesError = $this->syncUserRolesAndUnits($user, $roles, $data);
            if ($rolesError) {
                return $rolesError;
            }
        }

        // Send reset email if requested
        if (! empty($data['send_reset_email'])) {
            $token = PasswordBroker::createToken($user);
            $user->sendPasswordResetNotification($token);
        }

        // Send verification email if requested
        if (! empty($data['send_verification_email'])) {
            $user->email_verified_at = null;
            $user->save();
            $user->sendEmailVerificationNotification();
        }

        // If the updated user is the currently authenticated user, fire an event to update the user data in the UI
        if (Auth::id() === $user->id) {
            event(new UsimEvent('updated_profile', [
                'user' => $user,
            ]));
        }

        $fresh = $this->freshUserWithRoles($user);
        $freshData = $fresh->toArray();
        if (empty($freshData['roles']) && ! empty($freshData['global_roles'])) {
            $freshData['roles'] = $freshData['global_roles'];
        }

        return [
            'status' => 'success',
            'message' => 'Usuario actualizado exitosamente',
            'data' => $freshData,
        ];
    }

    /**
     * Synchronize user roles and unit memberships, managing transitions from lobby to operational units or main.
     *
     * @param  array<int, string>  $roles
     * @param  array<string, mixed>  $data
     * @return array{status: 'error', message: string, errors: array<string, string[]>}|null
     */
    protected function syncUserRolesAndUnits(User $user, array $roles, array $data): ?array
    {
        if (! config('permission.teams')) {
            return $this->syncRoles($user, $roles);
        }

        /** @var UsimUnitsService $unitsService */
        $unitsService = app(UsimUnitsService::class);
        $hasOperationalUnits = $unitsService->hasOperationalUnits();
        $isInLobby = $user->isRegisteredInLobby();

        $targetUnit = $this->resolveTargetUnitForSync($user, $data, $isInLobby, $hasOperationalUnits);

        if ($isInLobby && $hasOperationalUnits && ! $targetUnit) {
            return $this->validationError('target_unit', 'A valid operational unit is required.');
        }

        $previousTeamId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        try {
            if ($isInLobby) {
                $this->detachUserFromLobby($user, $targetUnit);
            }

            if ($targetUnit) {
                $user->usimUnits()->syncWithoutDetaching([$targetUnit->id]);
                setPermissionsTeamId($targetUnit->id);
            }

            $rolesError = $this->syncRoles($user, $roles);
            if ($rolesError) {
                return $rolesError;
            }
        } finally {
            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId($previousTeamId);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return null;
    }

    /**
     * Resolve the target unit for role synchronization based on lobby and operational unit state.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveTargetUnitForSync(
        User $user,
        array $data,
        bool $isInLobby,
        bool $hasOperationalUnits
    ): ?UsimUnit {
        $targetUnit = $this->findUnitByIdentifier($data['target_unit'] ?? null);
        if ($targetUnit) {
            return $targetUnit;
        }

        if ($isInLobby) {
            if ($hasOperationalUnits) {
                return UsimUnit::query()
                    ->where(function ($q) {
                        $q->where('type', '!=', 'system')->orWhereNull('type');
                    })
                    ->whereNotIn('slug', ['main', 'lobby'])
                    ->first();
            }

            return UsimUnit::where('slug', 'main')->first()
                ?? UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
        }

        return $user->usimUnits()->whereNotIn('slug', ['lobby'])->first()
            ?? UsimUnit::where('slug', 'main')->first()
            ?? UsimUnit::where('slug', 'lobby')->first();
    }

    /**
     * Find a UsimUnit by numeric ID or slug.
     */
    private function findUnitByIdentifier(mixed $identifier): ?UsimUnit
    {
        if (empty($identifier) || (! is_int($identifier) && ! is_string($identifier))) {
            return null;
        }

        return is_numeric($identifier)
            ? UsimUnit::find($identifier)
            : UsimUnit::where('slug', (string) $identifier)->first();
    }

    /**
     * Remove default lobby registration role and detach user from lobby unit when transitioning out.
     */
    private function detachUserFromLobby(User $user, ?UsimUnit $targetUnit): void
    {
        $lobbyUnit = UsimUnit::where('slug', 'lobby')->first();
        if (! $lobbyUnit) {
            return;
        }

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($lobbyUnit->id);
        }

        /** @var string $defaultRegRole */
        $defaultRegRole = config('usim.default_registering_role', 'registered');
        if ($user->hasRole($defaultRegRole)) {
            $user->removeRole($defaultRegRole);
        }

        if (! $targetUnit || $targetUnit->id !== $lobbyUnit->id) {
            $user->usimUnits()->detach($lobbyUnit->id);
        }
    }

    /**
     * Validate update payload for empty or null values
     *
     * @param  array<string, mixed>  $data
     * @return array{status: 'error', message: string, errors: array<string, string[]>}|null
     */
    private function validateUpdateData(array $data): ?array
    {
        foreach (['name', 'email', 'password'] as $field) {
            if ($this->isNullOrEmpty($data, $field)) {
                return $this->validationError($field, "The {$field} field cannot be empty.");
            }
        }

        if (
            array_key_exists('password', $data)
            && (! array_key_exists('password_confirmation', $data) || $this->isNullOrEmpty($data, 'password_confirmation'))
        ) {
            return $this->validationError('password_confirmation', 'The password confirmation field cannot be empty.');
        }

        if (array_key_exists('roles', $data)) {
            if ($data['roles'] === null || ! is_array($data['roles']) || $data['roles'] === []) {
                return $this->validationError('roles', 'The roles field must be a non-empty array.');
            }

            foreach ($data['roles'] as $roleName) {
                if (! is_string($roleName) || trim($roleName) === '') {
                    return $this->validationError('roles', 'Each role must be a non-empty string.');
                }
            }
        }

        return null;
    }

    /**
     * Build update data array based on provided payload, validating each field
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|array{status: 'error', message: string, errors: array<string, string[]>}
     */
    private function buildUpdateData(User $user, array $data): array
    {
        $updateData = [];

        if (array_key_exists('name', $data)) {
            if (! is_string($data['name']) || strlen($data['name']) > 255) {
                return $this->validationError('name', 'The name must be a string with max 255 characters.');
            }
            $updateData['name'] = trim($data['name']);
        }

        if (array_key_exists('email', $data)) {
            if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->validationError('email', 'The email must be a valid email address.');
            }

            $existingUser = User::where('email', $data['email'])
                ->where('id', '!=', $user->id)
                ->first();

            if ($existingUser) {
                return $this->validationError('email', 'The email has already been taken.');
            }

            $updateData['email'] = $data['email'];
        }

        if (array_key_exists('password', $data)) {
            if (! is_string($data['password']) || strlen($data['password']) < 8) {
                return $this->validationError('password', 'The password must be at least 8 characters.');
            }

            if ($data['password'] !== $data['password_confirmation']) {
                return $this->validationError('password', 'The password confirmation does not match.');
            }

            $updateData['password'] = Hash::make($data['password']);
        }

        return $updateData;
    }

    /**
     * Sync user roles with validation to prevent removing own admin role and ensure roles exist
     *
     * @param  array<int, string>  $roles
     * @return array{status: 'error', message: string, errors: array<string, string[]>}|null
     */
    private function syncRoles(User $user, array $roles): ?array
    {
        if (
            Auth::id() === $user->id
            && $user->hasRole('admin')
            && ! in_array('admin', $roles, true)
        ) {
            return [
                'status' => 'error',
                'message' => 'No puedes quitarte tu propio rol de administrador',
                'errors' => ['roles' => ['No puedes quitarte tu propio rol de administrador.']],
            ];
        }

        $invalidRoles = [];
        foreach ($roles as $roleName) {
            $roleExists = DB::table('roles')
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->exists();

            if (! $roleExists) {
                $invalidRoles[] = $roleName;
            }
        }

        if (! empty($invalidRoles)) {
            return $this->validationError('roles', 'The role ' . implode(', ', $invalidRoles) . ' does not exist.');
        }

        $user->syncRoles($roles);

        return null;
    }

    /**
     * Check if a field is null or empty string in the given data array
     *
     * @param  array<string, mixed>  $data
     */
    private function isNullOrEmpty(array $data, string $field): bool
    {
        if (! array_key_exists($field, $data)) {
            return false;
        }

        if ($data[$field] === null) {
            return true;
        }

        return is_string($data[$field]) && trim($data[$field]) === '';
    }

    /**
     * Build validation error response
     *
     * @return array{status: 'error', message: string, errors: array<string, string[]>}
     */
    private function validationError(string $field, string $message): array
    {
        return [
            'status' => 'error',
            'message' => 'Validation errors',
            'errors' => [$field => [$message]],
        ];
    }

    /**
     * Delete user with authorization check
     *
     * @return array{status: string, message: string}
     */
    public function deleteUser(Model $user): array
    {
        assert($user instanceof User);
        // Delete the user if it is different from the currently authenticated user
        if (Auth::id() === $user->id) {
            return [
                'status' => 'error',
                'message' => 'No se puede eliminar el usuario autenticado actualmente',
            ];
        }

        $userName = $user->name;
        $user->delete();

        return [
            'status' => 'success',
            'message' => "Usuario $userName eliminado exitosamente",
        ];
    }

    /**
     * Get paginated users list with search and sorting
     *
     * @param  array{per_page?: int, search?: string|null, sort_by?: string, sort_direction?: string, page?: int}  $params
     * @return array{status: 'success', message: string, data: array{users: array<int, array<string, mixed>>, pagination: array{current_page: int, total_pages: int, per_page: int, total_items: int}}}
     */
    public function getUsersList(array $params = []): array
    {
        $perPage = $params['per_page'] ?? 15;
        $sortBy = $params['sort_by'] ?? 'updated_at';
        $sortDirection = strtolower($params['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $search = $params['search'] ?? null;
        $page = $params['page'] ?? 1;

        if ($search === '') {
            $search = null;
        }

        $query = User::with('roles');
        $this->applySearchFilter($query, $search);

        // Ordenamiento
        if ($sortBy === 'roles') {
            $query->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->select('users.*', 'roles.name as role_name')
                ->orderBy('roles.name', $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $users = $query->paginate($perPage, ['*'], 'page', $page);

        // Transformar los datos para incluir roles como string
        $transformedUsers = $users->getCollection()->map(static function (User $user): array {
            $rolesString = $user->roles
                ->pluck('name')
                ->sort()
                ->values()
                ->implode(', ');

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->email_verified_at ? true : false,
                'roles' => $rolesString,
                'created_at' => $user->created_at?->diffForHumans() ?? '',
                'updated_at' => $user->updated_at?->diffForHumans() ?? '',
            ];
        });

        /** @var array<int, array<string, mixed>> $usersList */
        $usersList = $transformedUsers->toArray();

        /** @var array{current_page: int, total_pages: int, per_page: int, total_items: int} $pagination */
        $pagination = [
            'current_page' => $users->currentPage(),
            'total_pages' => $users->lastPage(),
            'per_page' => $users->perPage(),
            'total_items' => $users->total(),
        ];

        return [
            'status' => 'success',
            'message' => 'Usuarios recuperados exitosamente',
            'data' => [
                'users' => $usersList,
                'pagination' => $pagination,
            ],
        ];
    }

    /**
     * Count total users with optional search filter
     *
     * @param  string|null  $search  Search term
     * @return int Total count
     */
    public function countUsers(?string $search = null): int
    {
        if ($search === '') {
            $search = null;
        }

        $query = User::query();
        $this->applySearchFilter($query, $search);

        return $query->count();
    }

    /**
     * Apply search filter to query
     *
     * @param  Builder<User>  $query
     */
    private function applySearchFilter($query, ?string $search): void
    {
        $query->when($search, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhereHas('roles', function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%");
                    });
            });
        });
    }

    /**
     * Get a fresh user instance with roles loaded, falling back to the current model when refresh is unavailable.
     */
    private function freshUserWithRoles(User $user): User
    {
        $freshUser = $user->fresh();

        if ($freshUser === null) {
            $freshUser = $user;
        }

        $freshUser->load('roles');
        if (config('permission.teams')) {
            $freshUser->load('globalRoles');
        }
        $freshUser->load('usimUnits');

        return $freshUser;
    }

    /**
     * Verify user email with ID and hash
     *
     * @param  int  $id  User ID
     * @param  string  $hash  Email verification hash
     * @return array{success: bool, status: string, message: string}
     */
    public function verifyEmail(int $id, string $hash): array
    {
        $user = User::find($id);

        if (! $user) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Usuario no encontrado.',
            ];
        }

        // Verify hash matches user's email
        if (sha1($user->getEmailForVerification()) !== $hash) {
            return [
                'success' => false,
                'status' => 'invalid',
                'message' => 'Enlace de verificación inválido.',
            ];
        }

        // Check if already verified
        if ($user->hasVerifiedEmail()) {
            return [
                'success' => true,
                'status' => 'already_verified',
                'message' => 'Su email ya ha sido verificado anteriormente.',
            ];
        }

        // Mark as verified
        $user->markEmailAsVerified();

        // Fire Verified event assuming user implements MustVerifyEmail
        event(new Verified($user));

        // Fire custom UsimEvent for updating user data in the UI
        event(new UsimEvent('email_verified', [
            'user' => $user,
        ]));

        return [
            'success' => true,
            'status' => 'verified',
            'message' => 'Su email ha sido verificado satisfactoriamente.',
        ];
    }

    public function currentUser(): ?User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return null;
        }

        return $this->freshUserWithRoles($user);
    }

    /**
     * Check if the currently authenticated user has any of the specified roles.
     * 
     * @param  string|list<string>  $roles
     */
    public function currentUserHasRole(string|array $roles): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isRoot()) {
            return true;
        }

        $roleList = is_array($roles) ? $roles : [$roles];

        return $user->globalRoles()->whereIn('name', $roleList)->exists();
    }
}
