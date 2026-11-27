<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Auth;

use Tap\IdentityAccess\Application\Port\IssuedToken;

/**
 * El login tiene dos desenlaces exitosos:
 *  - authenticated: sesión emitida.
 *  - two_factor_required: la contraseña fue correcta, falta el código TOTP.
 */
final class LoginResult
{
    private function __construct(
        public readonly ?SessionTokens $session,
        public readonly ?IssuedToken $mfaToken,
    ) {
    }

    public static function authenticated(SessionTokens $session): self
    {
        return new self($session, null);
    }

    public static function twoFactorRequired(IssuedToken $mfaToken): self
    {
        return new self(null, $mfaToken);
    }

    public function requiresTwoFactor(): bool
    {
        return $this->mfaToken !== null;
    }
}
