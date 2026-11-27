<?php

declare(strict_types=1);

namespace Tap\Shared\Domain\Pagination;

/**
 * Resultado paginado genérico.
 *
 * @template T
 */
final class Page
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    /**
     * @template R
     *
     * @param callable(T): R $fn
     *
     * @return Page<R>
     */
    public function map(callable $fn): self
    {
        return new self(array_map($fn, $this->items), $this->total, $this->page, $this->perPage);
    }
}
