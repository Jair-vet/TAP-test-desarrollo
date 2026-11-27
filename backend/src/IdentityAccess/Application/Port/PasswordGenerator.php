<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Port;

/**
 * Puerto: genera contraseñas aleatorias (alta de usuario y recuperación).
 */
interface PasswordGenerator
{
    /** Contraseña aleatoria criptográficamente segura que cumple la política. */
    public function generate(int $length = 16): string;

    /**
     * Códigos de respaldo de 2FA (formato XXXXX-XXXXX).
     *
     * @return list<string>
     */
    public function recoveryCodes(int $count): array;
}
