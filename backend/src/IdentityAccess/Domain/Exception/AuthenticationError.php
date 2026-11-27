<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\Exception;

use DateTimeImmutable;
use DateTimeInterface;
use Tap\Shared\Domain\Exception\DomainError;

/**
 * Errores de autenticación (HTTP 401). Los mensajes son deliberadamente
 * genéricos: no revelan si falló el correo o la contraseña.
 */
final class AuthenticationError extends DomainError
{
    public static function invalidCredentials(): self
    {
        return new self('Correo o contraseña incorrectos.', 'INVALID_CREDENTIALS');
    }

    public static function accountLocked(DateTimeImmutable $until): self
    {
        return new self(
            'Cuenta bloqueada temporalmente por intentos fallidos.',
            'ACCOUNT_LOCKED',
            ['locked_until' => $until->format(DateTimeInterface::ATOM)],
        );
    }

    public static function unauthenticated(): self
    {
        return new self('Debes iniciar sesión.', 'UNAUTHENTICATED');
    }

    public static function tokenExpired(): self
    {
        return new self('La sesión expiró.', 'TOKEN_EXPIRED');
    }

    public static function tokenInvalid(): self
    {
        return new self('Token inválido.', 'TOKEN_INVALID');
    }

    public static function tokenRevoked(): self
    {
        return new self('La sesión fue cerrada.', 'TOKEN_REVOKED');
    }

    public static function invalidTwoFactorCode(): self
    {
        return new self('El código de verificación no es válido.', 'INVALID_TWO_FACTOR_CODE');
    }

    public static function invalidRefreshToken(): self
    {
        return new self('La sesión expiró, vuelve a iniciar sesión.', 'INVALID_REFRESH_TOKEN');
    }

    public static function refreshTokenReused(): self
    {
        return new self('Se detectó el reúso de una sesión. Por seguridad se cerraron todas las sesiones de este dispositivo.', 'REFRESH_TOKEN_REUSED');
    }

    public static function invalidPassword(): self
    {
        return new self('La contraseña actual no es correcta.', 'INVALID_PASSWORD');
    }
}
