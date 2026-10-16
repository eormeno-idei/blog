<?php
// @usim: feature="auth", type="request"

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'boolean',
        ];
    }

    /**
     * Normalize credentials from screen parameters or HTTP request data.
     *
     * @param array<string, mixed> $data
     * @return array{email: mixed, password: mixed, remember: bool}
     */
    public static function normalize(array $data): array
    {
        return [
            'email' => $data['email'] ?? $data['login_email'] ?? null,
            'password' => $data['password'] ?? $data['login_password'] ?? null,
            'remember' => filter_var($data['remember'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * Validate an arbitrary array (e.g., from UI Screen actions) against these rules.
     *
     * @param array<string, mixed> $data
     * @return array{email: string, password: string, remember: bool}
     *
     * @throws ValidationException
     */
    public static function validateData(array $data): array
    {
        $normalized = self::normalize($data);

        /** @var array{email: string, password: string, remember: bool} */
        return Validator::make($normalized, (new self())->rules())->validate();
    }
}
