<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\Exception;

use Tap\Shared\Domain\Exception\Conflict;

/** Conflictos de negocio del contexto de identidad (HTTP 409). */
final class IdentityConflict extends Conflict
{
    public static function emailTaken(): self
    {
        return new self('Ya existe un usuario con ese correo.', 'EMAIL_TAKEN', ['field' => 'email']);
    }

    public static function profileNameTaken(): self
    {
        return new self('Ya existe un perfil con ese nombre.', 'PROFILE_NAME_TAKEN', ['field' => 'name']);
    }

    public static function profileInUse(int $users): self
    {
        return new self(
            "No se puede eliminar: el perfil está asignado a {$users} usuario(s). Reasígnalos primero.",
            'PROFILE_IN_USE',
            ['users_count' => $users],
        );
    }

    public static function cannotDeleteSelf(): self
    {
        return new self('No puedes eliminar tu propio usuario.', 'CANNOT_DELETE_SELF');
    }

    public static function twoFactorAlreadyEnabled(): self
    {
        return new self('La verificación en dos pasos ya está activa.', 'TWO_FACTOR_ALREADY_ENABLED');
    }

    public static function twoFactorNotEnabled(): self
    {
        return new self('La verificación en dos pasos no está activa.', 'TWO_FACTOR_NOT_ENABLED');
    }

    public static function twoFactorSetupNotStarted(): self
    {
        return new self('Primero genera el código QR.', 'TWO_FACTOR_SETUP_NOT_STARTED');
    }

    public static function passwordReused(): self
    {
        return new self('La nueva contraseña debe ser distinta de la actual.', 'PASSWORD_REUSED', ['field' => 'password']);
    }
}
