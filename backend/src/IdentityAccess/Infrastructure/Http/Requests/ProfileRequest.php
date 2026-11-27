<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Tap\IdentityAccess\Application\Profile\ProfileInput;
use Tap\IdentityAccess\Domain\Profile\Action;
use Tap\IdentityAccess\Domain\Profile\Section;

/**
 * Alta/edición de perfil:
 * {
 *   "name": "Almacén",
 *   "description": "Opcional",
 *   "sections": [ { "section": "products", "actions": ["view","create","update"] } ]
 * }
 */
final class ProfileRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[\pL\pM\d][\pL\pM\d\s._\-]*$/u'],
            'description' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]|[\x00-\x1F\x7F]/'],
            'sections' => ['required', 'array', 'min:1', 'max:' . count(Section::cases())],
            'sections.*.section' => ['required', 'string', 'distinct', Rule::enum(Section::class)],
            'sections.*.actions' => ['required', 'array', 'min:1', 'max:' . count(Action::cases())],
            // Sin 'distinct' aquí: en Laravel compara contra TODAS las secciones (view en dos
            // secciones daría error). Los duplicados dentro de una sección los quita el dominio.
            'sections.*.actions.*' => ['required', 'string', Rule::enum(Action::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'description' => 'descripción', 'sections' => 'secciones'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'El nombre del perfil solo admite letras, números, espacios y . _ -',
            'description.not_regex' => 'La descripción no puede contener < > ni saltos de línea.',
        ];
    }

    public function toInput(): ProfileInput
    {
        /** @var list<array{section: string, actions: list<string>}> $sections */
        $sections = array_values(array_map(static fn (array $s): array => [
            'section' => (string) $s['section'],
            'actions' => array_values(array_map('strval', (array) $s['actions'])),
        ], (array) $this->validated('sections')));

        $description = $this->validated('description');

        return new ProfileInput(
            name: (string) $this->validated('name'),
            description: is_string($description) ? $description : null,
            sections: $sections,
        );
    }
}
