<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Tap\Shared\Domain\Pagination\ListQuery;

/**
 * Parámetros comunes de los listados: ?search=&page=&per_page=&sort=&direction=
 * Cada listado concreto declara sus columnas ordenables en {@see sortable()}.
 */
abstract class ListRequest extends FormRequest
{
    /** @return list<string> */
    abstract protected function sortable(): array;

    /**
     * Filtros exactos adicionales permitidos (nombre => reglas).
     *
     * @return array<string, array<int, mixed>>
     */
    protected function filterRules(): array
    {
        return [];
    }

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
            'search' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50, 100])],
            'sort' => ['nullable', 'string', Rule::in($this->sortable())],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ] + $this->filterRules();
    }

    public function toListQuery(): ListQuery
    {
        $filters = [];
        foreach (array_keys($this->filterRules()) as $name) {
            $value = $this->validated($name);
            if (is_string($value) && $value !== '') {
                $filters[$name] = $value;
            }
        }

        return new ListQuery(
            page: (int) $this->validated('page', 1),
            perPage: (int) $this->validated('per_page', 10),
            search: $this->validated('search'),
            sort: (string) $this->validated('sort', 'created_at'),
            direction: (string) $this->validated('direction', 'desc'),
            filters: $filters,
        );
    }
}
