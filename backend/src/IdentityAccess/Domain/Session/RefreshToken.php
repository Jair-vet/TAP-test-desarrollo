<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\Session;

use DateTimeImmutable;

/**
 * Refresh token opaco (no es JWT). En BD solo se guarda su SHA-256.
 *
 * Rotación con detección de reúso: cada refresh emite uno nuevo de la misma
 * "familia" y marca el anterior como usado. Si alguien presenta un token ya
 * usado (robado), se revoca TODA la familia y ambos (atacante y víctima)
 * deben volver a iniciar sesión.
 */
final class RefreshToken
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $familyId,
        public readonly string $tokenHash,
        public readonly DateTimeImmutable $expiresAt,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?string $ip,
        public readonly ?string $userAgent,
        private ?DateTimeImmutable $revokedAt = null,
        private ?string $replacedBy = null,
    ) {
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function rotate(string $replacedBy, DateTimeImmutable $now): void
    {
        $this->revokedAt = $now;
        $this->replacedBy = $replacedBy;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function replacedBy(): ?string
    {
        return $this->replacedBy;
    }
}
