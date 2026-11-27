<?php

declare(strict_types=1);

namespace Tests\Support;

use Tap\Catalog\Domain\Product;
use Tap\Catalog\Domain\ProductRepository;
use Tap\Shared\Domain\Pagination\ListQuery;
use Tap\Shared\Domain\Pagination\Page;

final class InMemoryProductRepository implements ProductRepository
{
    /** @var array<string, Product> */
    public array $items = [];
    private int $ids = 0;

    public function nextId(): string
    {
        return str_pad(dechex(++$this->ids), 24, '0', STR_PAD_LEFT);
    }

    public function find(string $id): ?Product
    {
        return $this->items[$id] ?? null;
    }

    public function save(Product $product): void
    {
        $this->items[$product->id()] = $product;
    }

    public function delete(Product $product): void
    {
        unset($this->items[$product->id()]);
    }

    public function paginate(ListQuery $query): Page
    {
        $all = array_values(array_filter(
            $this->items,
            static fn (Product $p): bool => $query->search === null || stripos($p->name(), $query->search) !== false,
        ));

        return new Page(array_slice($all, $query->offset(), $query->perPage), count($all), $query->page, $query->perPage);
    }

    public function cursor(ListQuery $query): iterable
    {
        yield from $this->paginate($query->withoutPagination())->items;
    }

    public function count(): int
    {
        return count($this->items);
    }
}
