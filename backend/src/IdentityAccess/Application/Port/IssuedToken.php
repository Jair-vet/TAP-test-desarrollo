<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Port;

/**
 * JWT recién firmado junto con su identificador (jti) y su expiración.
 */
final class IssuedToken
{
    public function __construct(
        public readonly string $token,
        public readonly string $id,
        public readonly int $expiresAt,
    ) {
    }

    public function expiresIn(int $now): int
    {
        return max(0, $this->expiresAt - $now);
    }
}
