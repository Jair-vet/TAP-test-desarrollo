<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\Exception;

use Tap\Shared\Domain\Exception\NotFound;

/**
 * Recuperación de contraseña para un correo inexistente.
 *
 * El examen pide explícitamente "Validar que el usuario existe en la base de
 * datos", así que se informa. Para mitigar la enumeración de usuarios, la
 * ruta tiene rate limiting estricto (ver AppServiceProvider: 'forgot').
 */
final class UserNotRegistered
{
    public static function forEmail(): NotFound
    {
        return new NotFound('No existe un usuario registrado con ese correo.', 'USER_NOT_FOUND');
    }
}
