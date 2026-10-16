<?php
// @usim: feature="auth", type="service"

namespace App\Services\Auth;

use Idei\Usim\Contracts\PasswordResetActionInterface;
use Idei\Usim\DTOs\PasswordResetResult;

class PasswordResetAction implements PasswordResetActionInterface
{
    public function __construct(
        protected PasswordService $passwordService
    ) {}

    public function sendResetLink(string $email): PasswordResetResult
    {
        $response = $this->passwordService->sendResetLink($email);

        if ($response['status'] !== 'success') {
            return PasswordResetResult::failed(
                message: $response['message'],
                errors: $response['errors']
            );
        }

        return PasswordResetResult::success($response['message']);
    }

    public function resetPassword(
        string $token,
        string $email,
        string $password,
        string $passwordConfirmation
    ): PasswordResetResult {
        $response = $this->passwordService->resetPassword(
            token: $token,
            email: $email,
            password: $password,
            passwordConfirmation: $passwordConfirmation
        );

        if ($response['status'] !== 'success') {
            return PasswordResetResult::failed(
                message: $response['message'],
                errors: $response['errors']
            );
        }

        return PasswordResetResult::success($response['message']);
    }
}
