<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /auth/two-factor/challenge — token MFA más un código TOTP de 6 dígitos o un código de respaldo.
 */
final class TwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'mfa_token' => ['required', 'string', 'max:4096'],
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'regex:/^\d{6}$/'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'regex:/^[A-Za-z0-9]{5}-?[A-Za-z0-9]{5}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'El código debe tener 6 dígitos.',
            'recovery_code.regex' => 'El código de respaldo tiene el formato XXXXX-XXXXX.',
        ];
    }
}
