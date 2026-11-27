<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Requests;

use Tap\Shared\Infrastructure\Http\Requests\ExportRequest;

/**
 * Parámetros de la exportación de usuarios a PDF o Excel (mismos filtros que la tabla).
 */
final class UserExportRequest extends ExportRequest
{
    protected function sortable(): array
    {
        return ['code', 'email', 'name', 'created_at'];
    }
}
