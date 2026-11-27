<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Http\Requests;

use Tap\Shared\Infrastructure\Http\Requests\ListRequest;

/**
 * Parámetros del listado de perfiles: búsqueda, paginación y orden (lista blanca de columnas).
 */
final class ProfileListRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['code', 'name', 'created_at'];
    }
}
