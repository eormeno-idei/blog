<?php

// @usim: feature="admin", type="service"

namespace App\Services\Auth;

use App\Models\User;
use Idei\Usim\Contracts\RegisterActionInterface;
use Idei\Usim\DTOs\RegisterData;
use Idei\Usim\DTOs\RegistrationResult;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterService implements RegisterActionInterface
{
    public function __construct(
        protected AuthSessionService $authSessionService
    ) {}

    /**
     * Summary of register
     *
     * @param  array<string>  $roles
     * @return array{
     *  status: string,
     *  message: string,
     *  user?: User,
     *  data?: array<string, mixed>,
     *  errors?: array<string, array<string>>}
     */
    public function register(
        string $name,
        string $email,
        string $password,
        string $passwordConfirmation,
        array $roles = ['user'],
        ?string $unit = null,
        bool $sendVerificationEmail = true,
    ): array {
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => t('service.auth.register.validation_errors'),
                'errors' => $validator->errors()->toArray(),
            ];
        }

        // Operación atómica de base de datos
        $user = DB::transaction(function () use ($name, $email, $password, $roles, $unit) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'terms_accepted_at' => now(),
            ]);

            // Asignación de unidad y contexto de Spatie si Teams está activo
            if (config('permission.teams')) {
                /** @var string $defaultRegisteringRole */
                $defaultRegisteringRole = config('usim.default_registering_role', 'registered');
                // Users registering with only the pending/default role must land in 'lobby'
                $isPendingRegistration = $roles === [$defaultRegisteringRole];
                $defaultSystemUnit = UsimUnit::where('slug', 'main')->exists() ? 'main' : 'lobby';
                $unitSlug = $unit ?? ($isPendingRegistration ? 'lobby' : $defaultSystemUnit);
                $modelUnit = UsimUnit::firstOrCreate(['slug' => $unitSlug]);

                $user->usimUnits()->syncWithoutDetaching([$modelUnit->id]);
                setPermissionsTeamId($modelUnit->id);
            }

            foreach ($roles as $role) {
                $user->assignRole($role);
            }

            return $user;
        });

        if ($sendVerificationEmail) {
            event(new Registered($user));
        }

        $token = $this->authSessionService->issueToken($user);

        return [
            'status' => 'success',
            'message' => t('service.auth.register.success'),
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->toArray(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                ],
                'token' => $token,
            ],
            'user' => $user,
        ];
    }

    public function execute(RegisterData $data, ?bool $startSession = null): RegistrationResult
    {
        if (! $data->acceptTerms) {
            return RegistrationResult::failed(
                message: t('screens.auth.register.terms_required'),
            );
        }

        $response = $this->register(
            name: $data->name,
            email: $data->email,
            password: $data->password,
            passwordConfirmation: $data->passwordConfirmation,
            roles: $data->roles,
            unit: $data->unit,
            sendVerificationEmail: $data->sendVerificationEmail,
        );

        if ($response['status'] !== 'success') {
            /** @var array<string, list<string>> $errors */
            $errors = $response['errors'] ?? [];

            return RegistrationResult::failed(
                message: $response['message'],
                errors: $errors
            );
        }

        $rawToken = data_get($response, 'data.token');
        $token = is_string($rawToken) ? $rawToken : null;
        $user = $response['user'] ?? null;
        $payloadData = $response['data'] ?? [];

        if ($user instanceof User) {
            $homeScreen = $this->authSessionService->resolveHomeScreen($user, $data->unit);
            $payloadData['home_screen'] = $homeScreen;

            $shouldStartSession = $startSession ?? ! Auth::check();
            if ($shouldStartSession) {
                $this->authSessionService->establishSession($user, $data->unit, $token, $homeScreen);
            }
        }

        return RegistrationResult::success(
            message: $response['message'],
            user: $user,
            token: $token,
            data: $payloadData,
        );
    }
}
