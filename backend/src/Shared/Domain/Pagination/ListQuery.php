<?php

declare(strict_types=1);

namespace Tap\Shared\Domain\Pagination;

/**
 * Criterios de listado (búsqueda + orden + paginación) independientes de la BD.
 * Cada repositorio decide cómo traducirlos (Mongo hoy, Dynamo/Firestore mañana).
 */
final class ListQuery
{
    public const MAX_PER_PAGE = 100;

    /**
     * @param array<string, string> $filters Filtros exactos ya validados (ej. entity_type)
     */
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 10,
        public readonly ?string $search = null,
        public readonly string $sort = 'created_at',
        public readonly string $direction = 'desc',
        public readonly array $filters = [],
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Huella estable para usarla como llave de caché.
     *
     * @return array<string, mixed>
     */
    public function fingerprint(): array
    {
        $filters = $this->filters;
        ksort($filters);

        return [
            'p' => $this->page,
            'pp' => $this->perPage,
            'q' => $this->search,
            's' => $this->sort,
            'd' => $this->direction,
            'f' => $filters,
        ];
    }

    /** Misma consulta sin paginar (para exportar todo lo filtrado). */
    public function withoutPagination(): self
    {
        return new self(1, PHP_INT_MAX, $this->search, $this->sort, $this->direction, $this->filters);
    }
}
