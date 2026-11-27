<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Propaganistas\LaravelPhone\PhoneNumber;
use Tap\IdentityAccess\Application\User\UserInput;

/**
 * Alta / edición de usuario (multipart/form-data por la foto).
 *
 * Campos del examen:
 *  - Nombre (requerido)
 *  - Foto de perfil (requerido en alta; opcional al editar)
 *  - Usuario: correo electrónico único (requerido)
 *  - Teléfono opcional, debe incluir código de país (+52...)
 *
 * Para editar con foto desde un form HTML: POST /users/{id} con _method=PUT
 * (PHP no parsea multipart en PUT nativo).
 */
final class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST') && $this->route('id') === null;
        $maxKb = (int) config('security.media.max_photo_kb', 2048);

        return [
            // Nombre de persona: letras (con acentos), espacios y . ' -
            'name' => ['required', 'string', 'min:3', 'max:120', "regex:/^[\\pL\\pM][\\pL\\pM\\s.'\\-]*$/u"],
            'email' => ['required', 'string', 'max:150', 'email:strict', 'not_regex:/[\x00-\x1F\x7F]/', 'regex:/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/'],
            // phone:INTERNATIONAL → libphonenumber valida longitud real según el país del prefijo
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^\+[1-9][\d\s\-().]{6,22}$/', 'phone:INTERNATIONAL'],
            'photo' => [
                $creating ? 'required' : 'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                "max:{$maxKb}",
                'dimensions:min_width=64,min_height=64,max_width=6000,max_height=6000',
            ],
            'profile_ids' => ['nullable', 'array', 'max:20'],
            'profile_ids.*' => ['string', 'distinct', 'regex:/^[a-f0-9]{24}$/i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'El nombre solo admite letras, espacios y . \' -',
            'email.regex' => 'Escribe un correo válido (ej. nombre@empresa.com).',
            'phone.regex' => 'El teléfono debe iniciar con + y el código de país, ej. +52 314 123 4567.',
            'phone.phone' => 'El teléfono no es válido para el código de país indicado.',
            'photo.required' => 'La foto de perfil es obligatoria.',
            'photo.dimensions' => 'La foto debe medir al menos 64x64 px.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'email' => 'usuario (correo)', 'phone' => 'teléfono', 'photo' => 'foto de perfil', 'profile_ids' => 'perfiles'];
    }

    public function toInput(): UserInput
    {
        $phone = $this->validated('phone');

        return new UserInput(
            name: (string) $this->validated('name'),
            email: (string) $this->validated('email'),
            phone: is_string($phone) && $phone !== '' ? (new PhoneNumber($phone))->formatE164() : null,
            profileIds: array_values(array_map('strval', (array) $this->validated('profile_ids', []))),
        );
    }

    public function photoPath(): ?string
    {
        $file = $this->file('photo');

        return $file instanceof UploadedFile && $file->isValid() ? $file->getRealPath() ?: null : null;
    }
}
