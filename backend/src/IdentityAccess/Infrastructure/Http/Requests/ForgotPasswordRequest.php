<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /auth/forgot-password — valida el correo antes de buscar al usuario.
 */
final class ForgotPasswordRequest extends FormRequest
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
        // 'email:strict' + rechazo explícito de caracteres de control (CRLF):
        // mitigación del advisory GHSA-5vg9-5847-vvmq en Laravel 11.
        return [
            'email' => ['required', 'string', 'max:150', 'email:strict', 'not_regex:/[\x00-\x1F\x7F]/', 'regex:/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'correo'];
    }
}
