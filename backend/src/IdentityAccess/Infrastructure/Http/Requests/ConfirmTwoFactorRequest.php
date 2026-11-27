<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /auth/two-factor/confirm — código de 6 dígitos que demuestra que el QR se escaneó bien.
 */
final class ConfirmTwoFactorRequest extends FormRequest
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
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.regex' => 'El código debe tener 6 dígitos.'];
    }
}
