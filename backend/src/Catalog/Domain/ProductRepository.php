<?php

declare(strict_types=1);

namespace Tap\Catalog\Domain;

use Tap\Shared\Domain\Pagination\ListQuery;
use Tap\Shared\Domain\Pagination\Page;

/**
 * Puerto de persistencia de productos. El dominio y la aplicación solo
 * conocen esta interfaz; MongoProductRepository es el adaptador actual.
 */
interface ProductRepository
{
    public function nextId(): string;

    public function find(string $id): ?Product;

    public function save(Product $product): void;

    public function delete(Product $product): void;

    /** @return Page<Product> */
    public function paginate(ListQuery $query): Page;

    /** @return iterable<Product> Cursor perezoso (exportaciones grandes sin agotar memoria) */
    public function cursor(ListQuery $query): iterable;

    public function count(): int;
}
