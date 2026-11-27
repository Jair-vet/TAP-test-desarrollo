<?php

declare(strict_types=1);

namespace Tap\Catalog\Infrastructure\Http;

use Illuminate\Foundation\Http\FormRequest;
use Tap\Catalog\Application\ProductData;

/**
 * Validación de alta/edición de productos (primera barrera; el dominio
 * vuelve a validar en Price y Product).
 */
final class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización la hace el middleware permission:products.*
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // not_regex: sin etiquetas HTML (defensa en profundidad contra XSS almacenado)
            'name' => ['required', 'string', 'min:2', 'max:120', 'not_regex:/[<>]|[\x00-\x1F\x7F]/'],
            'brand' => ['required', 'string', 'min:1', 'max:80', 'not_regex:/[<>]|[\x00-\x1F\x7F]/'],
            // Máximo 3 dígitos enteros y 2 decimales: 0.01 – 999.99
            'price' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.not_regex' => 'El nombre no puede contener < > ni saltos de línea.',
            'brand.not_regex' => 'La marca no puede contener < > ni saltos de línea.',
            'price.regex' => 'El precio admite máximo 3 dígitos enteros y 2 decimales (ej. 999.99).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'brand' => 'marca', 'price' => 'precio'];
    }

    public function toData(): ProductData
    {
        return new ProductData(
            name: (string) $this->validated('name'),
            brand: (string) $this->validated('brand'),
            price: (string) $this->validated('price'),
        );
    }
}
