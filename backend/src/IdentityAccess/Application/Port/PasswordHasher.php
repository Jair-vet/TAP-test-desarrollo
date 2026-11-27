<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Port;

/**
 * Puerto de cifrado de contraseñas (implementado con Argon2id).
 * needsRehash() permite subir los parámetros sin obligar a nadie a cambiar su contraseña.
 */
interface PasswordHasher
{
    public function hash(string $plain): string;

    public function verify(string $plain, string $hash): bool;

    public function needsRehash(string $hash): bool;
}
