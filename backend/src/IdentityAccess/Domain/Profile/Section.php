<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\Profile;

/**
 * Secciones del sistema que se asignan a los perfiles.
 * "Los usuarios solo podrán ingresar a las secciones asignadas en sus perfiles."
 *
 * Para agregar una sección nueva (ej. "Reportes"): agrega el case aquí, su
 * etiqueta, sus acciones permitidas, y protege sus rutas con
 * ->middleware('permission:reports,view'). El frontend la toma de GET /sections.
 */
enum Section: string
{
    case Products = 'products';
    case Users = 'users';
    case Profiles = 'profiles';
    case Audit = 'audit';

    public function label(): string
    {
        return match ($this) {
            self::Products => 'Productos',
            self::Users => 'Usuarios',
            self::Profiles => 'Perfiles',
            self::Audit => 'Bitácora',
        };
    }

    /** @return list<Action> */
    public function allowedActions(): array
    {
        return match ($this) {
            self::Audit => [Action::View, Action::Export],
            default => Action::cases(),
        };
    }
}
