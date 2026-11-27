<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\Session;

use DateTimeImmutable;

/**
 * Puerto de persistencia de refresh tokens (solo se guarda su hash SHA-256).
 * revokeFamily() corta toda la cadena de rotación si se detecta un token reutilizado.
 */
interface RefreshTokenRepository
{
    public function nextId(): string;

    public function save(RefreshToken $token): void;

    public function findByHash(string $tokenHash): ?RefreshToken;

    public function revokeFamily(string $familyId, DateTimeImmutable $now): void;

    public function revokeAllForUser(string $userId, DateTimeImmutable $now, ?string $exceptFamilyId = null): void;
}
