<?php

declare(strict_types=1);

namespace Tap\Catalog\Infrastructure\Http;

use Tap\Shared\Infrastructure\Http\Requests\ListRequest;

/**
 * Parámetros del listado de productos: búsqueda, paginación y orden (lista blanca de columnas).
 */
final class ProductListRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['code', 'name', 'brand', 'price', 'created_at'];
    }
}
