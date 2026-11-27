<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\Exception;

use Tap\Shared\Domain\Exception\DomainError;

/** Errores de autorización (HTTP 403). */
final class AuthorizationError extends DomainError
{
    public static function forbidden(string $section, string $action): self
    {
        return new self(
            'No tienes permiso para realizar esta acción.',
            'FORBIDDEN',
            ['section' => $section, 'action' => $action],
        );
    }

    public static function passwordChangeRequired(): self
    {
        return new self('Debes cambiar tu contraseña antes de continuar.', 'PASSWORD_CHANGE_REQUIRED');
    }

    public static function twoFactorRequired(): self
    {
        return new self('Debes activar la verificación en dos pasos antes de continuar.', 'TWO_FACTOR_SETUP_REQUIRED');
    }
}
