<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /auth/login — correo y contraseña como texto con longitud máxima.
 * Cualquier fallo de credenciales responde el mismo mensaje genérico (no revela si la cuenta existe).
 */
final class LoginRequest extends FormRequest
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
            // Solo 'string': si llega un objeto/arreglo ({"$ne": null}) falla aquí
            'email' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'correo', 'password' => 'contraseña'];
    }
}
