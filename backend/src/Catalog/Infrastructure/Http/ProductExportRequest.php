<?php

declare(strict_types=1);

namespace Tap\Catalog\Infrastructure\Http;

use Tap\Shared\Infrastructure\Http\Requests\ExportRequest;

/**
 * Parámetros de la exportación de productos a PDF o Excel (mismos filtros que la tabla).
 */
final class ProductExportRequest extends ExportRequest
{
    protected function sortable(): array
    {
        return ['code', 'name', 'brand', 'price', 'created_at'];
    }
}
