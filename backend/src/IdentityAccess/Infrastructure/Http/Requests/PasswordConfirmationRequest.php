<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pide la contraseña actual antes de una acción sensible (p. ej. regenerar códigos de respaldo).
 */
final class PasswordConfirmationRequest extends FormRequest
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
        return ['password' => ['required', 'string', 'max:200']];
    }
}
