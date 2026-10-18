<?php

// @usim: feature="auth", type="request"

namespace App\Http\Requests\Auth;

use Idei\Usim\DTOs\RegisterData;
use Idei\Usim\DTOs\RegistrationResult;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    /**
     * Normalize screen or request parameters into a RegisterData DTO.
     *
     * @param  array<string, mixed>  $params
     */
    public static function toRegisterData(array $params, bool $askForRole = false): RegisterData
    {
        $acceptTerms = $askForRole || (bool) ($params['accept_terms'] ?? false);

        return new RegisterData(
            name: \is_string($params['name'] ?? null) ? $params['name'] : '',
            email: \is_string($params['email'] ?? null) ? $params['email'] : '',
            password: \is_string($params['password'] ?? null) ? $params['password'] : '',
            passwordConfirmation: \is_string($params['password_confirmation'] ?? null) ? $params['password_confirmation'] : '',
            roles: self::normalizeRoles($params['roles'] ?? null),
            sendVerificationEmail: ! isset($params['send_verification_email']) || (bool) $params['send_verification_email'],
            acceptTerms: $acceptTerms,
        );
    }

    /**
     * Normalize role input into a non-empty list of role names.
     *
     * @return list<string>
     */
    public static function normalizeRoles(mixed $rawRoles): array
    {
        /** @var string $defaultRole */
        $defaultRole = config('usim.default_registering_role', 'registered');

        if (\is_string($rawRoles) && $rawRoles !== '') {
            return [$rawRoles];
        }

        if (! \is_array($rawRoles)) {
            return [$defaultRole];
        }

        /** @var list<string> $roles */
        $roles = array_values(array_filter($rawRoles, static fn (mixed $role): bool => \is_string($role) && $role !== ''));

        return $roles !== [] ? $roles : [$defaultRole];
    }

    /**
     * Flatten validation error messages from a RegistrationResult into a single UI message.
     */
    public static function formatErrorMessage(RegistrationResult $result): string
    {
        if (empty($result->errors)) {
            return $result->message;
        }

        $errorMessages = [];
        foreach ($result->errors as $fieldErrors) {
            foreach ($fieldErrors as $err) {
                $errorMessages[] = $err;
            }
        }

        return $errorMessages !== [] ? implode(' ', $errorMessages) : $result->message;
    }
}

