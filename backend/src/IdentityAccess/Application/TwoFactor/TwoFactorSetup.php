<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\TwoFactor;

/** Datos para enrolar la app autenticadora (QR + clave manual). */
final class TwoFactorSetup
{
    public function __construct(
        public readonly string $secret,
        public readonly string $provisioningUri,
        public readonly string $qrSvg,
    ) {
    }
}
