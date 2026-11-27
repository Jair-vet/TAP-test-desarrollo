<?php

declare(strict_types=1);

namespace Tap\Shared\Application;

/**
 * Usuario autenticado en la petición actual (lo arma el middleware JWT).
 * Vive en Shared porque todos los contextos lo necesitan para autorizar y
 * auditar, sin acoplarse al contexto IdentityAccess.
 */
final class AuthenticatedUser
{
    /**
     * @param array<string, list<string>> $permissions sección => acciones
     */
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly string $name,
        public readonly string $email,
        public readonly array $permissions,
        public readonly bool $mustChangePassword,
        public readonly bool $twoFactorEnabled,
        public readonly string $tokenId,
        public readonly int $tokenExpiresAt,
    ) {
    }

    public function can(string $section, string $action): bool
    {
        return in_array($action, $this->permissions[$section] ?? [], true);
    }

    public function toActor(?string $ip, ?string $userAgent): Actor
    {
        return new Actor($this->id, $this->code, $this->name, $this->email, $ip, $userAgent);
    }
}
