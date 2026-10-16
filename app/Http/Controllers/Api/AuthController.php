<?php
// @usim: feature="admin", type="controller"
namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\LoginService;
use App\Services\Auth\RegisterService;
use App\Services\Auth\PasswordService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        protected PasswordService $passwordService
    ) {
    }
    public function register(Request $request, RegisterService $registerService): JsonResponse
    {
        $response = $registerService->register(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            passwordConfirmation: $request->string('password_confirmation')->toString(),
            roles: $this->normalizeRoles($request->input('roles', ['user'])),
            sendVerificationEmail: $request->boolean('send_verification_email', true),
        );

        $httpStatus = $response['status'] === 'success' ? 201 : 422;

        // Remove the Eloquent user model from the API response
        unset($response['user']);

        return response()->json($response, $httpStatus);
    }

    public function login(LoginRequest $request, LoginService $loginService): JsonResponse
    {
        $response = $loginService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember')
        );

        $httpStatus = $response['status'] === 'success' ? 200 : 401;

        unset($response['user']);

        return response()->json($response, $httpStatus);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var PersonalAccessToken|null $accessToken */
        $accessToken = $user->currentAccessToken();
        $accessToken?->delete();

        return response()->json([
            'status' => 'success',
            'data' => null,
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        /** @var int $userId */
        $userId = (int) $request->route('id');
        $user = User::query()->find($userId);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
                'errors' => null
            ], 404);
        }

        // Verificar que el hash coincida con el email del usuario
        $expectedHash = sha1($user->email);
        /** @var string $providedHash */
        $providedHash = (string) $request->route('hash');

        if ($expectedHash !== $providedHash) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid verification link',
                'errors' => null
            ], 400);
        }

        // El middleware 'signed' ya validó la firma y expiración
        // Si llegamos aquí, la URL es válida

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'success',
                'data' => null,
                'message' => 'Email already verified'
            ], 200);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'status' => 'success',
            'data' => null,
            'message' => 'Email verified successfully'
        ], 200);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'success',
                'data' => null,
                'message' => 'Email already verified'
            ], 200);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'status' => 'success',
            'data' => null,
            'message' => 'Verification email sent'
        ], 200);

    }

    /**
     * Obtener usuario autenticado
     */
    public function user(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Obtener permisos usando Spatie
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        $roles = $user->getRoleNames()->toArray();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $roles,
                    'permissions' => $permissions,
                ],
            ]
        ]);
    }

    /**
     * Enviar enlace de reset de contraseña
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $response = $this->passwordService->sendResetLink(
            $request->string('email')->toString()
        );

        $httpStatus = $response['status'] === 'success' ? 200 : 400;
        return response()->json($response, $httpStatus);
    }

    /**
     * Resetear la contraseña
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $response = $this->passwordService->resetPassword(
            token: $request->string('token')->toString(),
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            passwordConfirmation: $request->string('password_confirmation')->toString()
        );

        $httpStatus = $response['status'] === 'success' ? 200 : 422;
        return response()->json($response, $httpStatus);
    }

    /**
     * @param mixed $roles
     * @return list<string>
     */
    private function normalizeRoles(mixed $roles): array
    {
        if (is_string($roles)) {
            return [$roles];
        }

        if (!is_array($roles)) {
            return ['user'];
        }

        $normalized = [];
        foreach ($roles as $role) {
            if (is_string($role)) {
                $normalized[] = $role;
            }
        }

        return $normalized !== [] ? $normalized : ['user'];
    }
}
