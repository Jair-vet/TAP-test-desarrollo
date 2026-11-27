<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Port;

/** Cifrado simétrico autenticado para secretos en reposo (semilla TOTP). */
interface SecretCipher
{
    public function encrypt(string $plain): string;

    public function decrypt(string $cipher): string;
}
