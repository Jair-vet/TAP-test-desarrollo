<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Port;

use Tap\IdentityAccess\Domain\User\User;

/**
 * Emisión/validación de JWT.
 *  - access: token corto (10 min) que viaja en Authorization: Bearer.
 *  - mfa: token de un solo propósito (5 min) entre la contraseña y el código 2FA.
 */
interface TokenService
{
    public const TYPE_ACCESS = 'access';
    public const TYPE_MFA = 'mfa';

    public function issue(User $user, string $type): IssuedToken;

    /**
     * Valida firma, emisor, audiencia, expiración y tipo.
     *
     * @throws \Tap\IdentityAccess\Domain\Exception\AuthenticationError
     */
    public function decode(string $token, string $expectedType): TokenClaims;
}
