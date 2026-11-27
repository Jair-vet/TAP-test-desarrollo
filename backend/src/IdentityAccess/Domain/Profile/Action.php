<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\Profile;

/** Acciones granulares dentro de una sección ("acciones para usuarios autorizados"). */
enum Action: string
{
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Export = 'export';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Consultar',
            self::Create => 'Crear',
            self::Update => 'Editar',
            self::Delete => 'Eliminar',
            self::Export => 'Exportar',
        };
    }
}
