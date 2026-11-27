<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Port;

/**
 * Lista de JWT revocados (logout, token MFA ya usado). Guarda el `jti`
 * solo hasta que el token expira: después ya no hace falta recordarlo.
 */
interface TokenRevocationList
{
    public function revoke(string $tokenId, int $expiresAt): void;

    public function isRevoked(string $tokenId): bool;
}
