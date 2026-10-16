<?php
// @usim: feature="admin", type="service"
namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PasswordService
{
    /**
     * Send password reset link
     *
     * @param string $email
        * @return array{
        *     status: 'error',
        *     message: string,
        *     errors: array<string, list<string>>
        * }|array{
        *     status: 'success',
        *     data: null,
        *     message: string
        * }
     */
    public function sendResetLink(string $email): array
    {
        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => t('service.auth.password.validation_errors'),
                'errors' => $validator->errors()->toArray(),
            ];
        }

        // Verificar que el usuario existe
        $user = User::where('email', $email)->first();

        if (!$user) {
            return [
                'status' => 'error',
                'message' => t('service.auth.password.user_not_found'),
                'errors' => ['email' => [t('service.auth.password.user_not_found')]],
            ];
        }

        $status = PasswordBroker::sendResetLink(['email' => $email]);

        if ($status === PasswordBroker::RESET_LINK_SENT) {
            return [
                'status' => 'success',
                'data' => null,
                'message' => t('service.auth.password.reset_link_sent'),
            ];
        }

        return [
            'status' => 'error',
            'message' => t('service.auth.password.reset_link_failed'),
            'errors' => ['email' => [t('service.auth.password.reset_link_failed')]],
        ];
    }

    /**
     * Reset password
     *
     * @param string $token
     * @param string $email
     * @param string $password
     * @param string $passwordConfirmation
     * @return array{
     *     status: 'error',
     *     message: string,
     *     errors: array<string, list<string>>
     * }|array{
     *     status: 'success',
     *     data: null,
     *     message: string
     * }
     */
    public function resetPassword(
        string $token,
        string $email,
        string $password,
        string $passwordConfirmation
    ): array {
        $validator = Validator::make([
            'token' => $token,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => t('service.auth.password.validation_errors'),
                'errors' => $validator->errors()->toArray(),
            ];
        }

        $status = PasswordBroker::reset(
            [
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
                'token' => $token,
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === PasswordBroker::PASSWORD_RESET) {
            return [
                'status' => 'success',
                'data' => null,
                'message' => t('service.auth.password.reset_success'),
            ];
        }

        return [
            'status' => 'error',
            'message' => t('service.auth.password.reset_failed'),
            'errors' => ['email' => [__(is_string($status) ? $status : null)]],
        ];
    }
}
