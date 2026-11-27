<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Auth;

use Tap\IdentityAccess\Application\Port\IssuedToken;
use Tap\IdentityAccess\Domain\Profile\PermissionSet;
use Tap\IdentityAccess\Domain\User\User;

/** Resultado de iniciar/renovar sesión: JWT de acceso + refresh token opaco. */
final class SessionTokens
{
    public function __construct(
        public readonly IssuedToken $accessToken,
        public readonly string $refreshToken,
        public readonly int $refreshExpiresAt,
        public readonly User $user,
        public readonly PermissionSet $permissions,
    ) {
    }
}
