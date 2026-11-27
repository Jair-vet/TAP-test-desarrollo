<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Port;

/**
 * Datos de un JWT ya verificado (sujeto, tipo, jti, expiración y versión de token).
 * Lo devuelve TokenService::decode() solo si la firma, el tipo y las fechas son válidos.
 */
final class TokenClaims
{
    public function __construct(
        public readonly string $subject,
        public readonly string $tokenId,
        public readonly string $type,
        public readonly int $tokenVersion,
        public readonly int $expiresAt,
    ) {
    }
}
