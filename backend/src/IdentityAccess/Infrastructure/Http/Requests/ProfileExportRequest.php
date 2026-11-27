<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Requests;

use Tap\Shared\Infrastructure\Http\Requests\ExportRequest;

/**
 * Parámetros de la exportación de perfiles a PDF o Excel (mismos filtros que la tabla).
 */
final class ProfileExportRequest extends ExportRequest
{
    protected function sortable(): array
    {
        return ['code', 'name', 'created_at'];
    }
}
