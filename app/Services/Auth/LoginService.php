<?php

// @usim: feature="admin", type="service"

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Units\UnitContextResolver;
use App\Services\Units\UsimUnitsService;
use Idei\Usim\Contracts\LoginActionInterface;
use Idei\Usim\DTOs\AuthResult;
use Idei\Usim\DTOs\LoginCredentials;
use Idei\Usim\Enums\AuthStatus;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Illuminate\Support\Facades\Hash;

class LoginService implements LoginActionInterface
{
    public function __construct(
        protected AuthSessionService $authSessionService,
        protected UsimUnitsService $usimUnitsService
    ) {}

    /**
     * @return array{
     *     status: 'error',
     *     message: string,
     *     errors: array<string, list<string>>
     * }|array{
     *     status: 'success',
     *     message: string,
     *     data: array{
     *         user: array{
     *             id: int,
     *             name: string,
     *             email: string,
     *             roles: list<string>,
     *             permissions: list<string>
     *         }
     *     },
     *     user: User,
     *     token: string,
     *     units: array<string, array<mixed>>,
     *     active_unit: string|null,
     *     home_screen: class-string<Screen>,
     *     redirect_to: string
     * }
     */
    public function login(
        string $email,
        string $password,
        bool $remember = false,
        ?string $unit = null,
        bool $startSession = false
    ): array {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $message = t('service.auth.login.invalid_credentials');
            $errors = ['email' => [t('service.auth.login.invalid_credentials_detail')]];

            event(new UsimEvent('login_failed', [
                'email' => $email,
                'message' => $message,
                'errors' => $errors,
            ]));

            return [
                'status' => 'error',
                'message' => $message,
                'errors' => $errors,
            ];
        }

        $token = $this->authSessionService->issueToken($user, $remember);

        /** @var list<string> $permissions */
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        /** @var list<string> $roles */
        $roles = $user->getRoleNames()->toArray();

        // Resolve units with roles
        $unitsWithRoles = $this->usimUnitsService->getUserUnitsWithRoles($user);

        // Resolve active unit context (fallback to first user unit if not specified)
        $activeUnit = $unit;
        if (config('permission.teams', false)) {
            $resolvedUnit = UnitContextResolver::resolve($user, $unit);
            $activeUnit = $resolvedUnit?->slug;
        }

        // Resolve home screen and redirect path
        $homeScreen = $this->authSessionService->resolveHomeScreen($user, $activeUnit);
        $redirectTo = $homeScreen::getRoutePath();

        if ($startSession) {
            $this->authSessionService->establishSession($user, $activeUnit, $token, $homeScreen);
        }

        return [
            'status' => 'success',
            'message' => t('service.auth.login.success'),
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $roles,
                    'permissions' => $permissions,
                ],
            ],
            'user' => $user,
            'token' => $token,
            'units' => $unitsWithRoles,
            'active_unit' => $activeUnit,
            'home_screen' => $homeScreen,
            'redirect_to' => $redirectTo,
        ];
    }

    public function execute(LoginCredentials $credentials, bool $startSession = true): AuthResult
    {
        $response = $this->login(
            $credentials->email,
            $credentials->password,
            $credentials->remember,
            $credentials->unit,
            $startSession
        );

        if ($response['status'] !== 'success') {
            return AuthResult::failed(
                status: AuthStatus::BAD_CREDENTIALS,
                message: $response['message'],
                errors: $response['errors']
            );
        }

        return AuthResult::success(
            redirectTo: $response['redirect_to'],
            token: $response['token'],
            message: $response['message'],
            user: $response['user'],
            homeScreen: $response['home_screen'],
            activeUnit: $response['active_unit'],
            data: $response['data'],
        );
    }
}
