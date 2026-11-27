<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\User;

use DateTimeImmutable;

/**
 * Contraseña temporal emitida por "Olvidé mi contraseña". Convive con la
 * contraseña actual hasta que expira o se usa, así que solicitar una
 * recuperación no deja fuera al dueño real de la cuenta.
 */
final class TemporaryPassword
{
    public function __construct(
        public readonly string $hash,
        public readonly DateTimeImmutable $expiresAt,
    ) {
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }
}
