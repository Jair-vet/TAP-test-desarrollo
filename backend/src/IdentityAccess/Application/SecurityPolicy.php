<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application;

/**
 * Parámetros de seguridad inyectados desde config/security.php.
 * La aplicación no lee config() directamente: así es testeable.
 */
final class SecurityPolicy
{
    public function __construct(
        public readonly int $maxLoginAttempts = 5,
        public readonly int $lockoutMinutes = 15,
        public readonly int $refreshTtlSeconds = 604800,
        public readonly int $temporaryPasswordTtlMinutes = 60,
        public readonly int $recoveryCodesCount = 8,
        public readonly bool $twoFactorRequired = false,
    ) {
    }
}
