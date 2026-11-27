<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\Profile;

/**
 * Permisos efectivos de un usuario = unión de las secciones/acciones de
 * todos sus perfiles. Inmutable.
 */
final class PermissionSet
{
    /**
     * @param array<string, list<string>> $map sección => acciones
     */
    private function __construct(private readonly array $map)
    {
    }

    /**
     * @param list<Profile> $profiles
     */
    public static function fromProfiles(array $profiles): self
    {
        $map = [];
        foreach ($profiles as $profile) {
            foreach ($profile->sections() as $access) {
                $current = $map[$access->section->value] ?? [];
                $map[$access->section->value] = array_values(array_unique([...$current, ...$access->actionValues()]));
            }
        }

        ksort($map);

        return new self($map);
    }

    public function can(Section $section, Action $action): bool
    {
        return in_array($action->value, $this->map[$section->value] ?? [], true);
    }

    /** @return array<string, list<string>> */
    public function toArray(): array
    {
        return $this->map;
    }
}
