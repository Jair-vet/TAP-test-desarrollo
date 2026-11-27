<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Security;

use Illuminate\Contracts\Encryption\StringEncrypter;
use Tap\IdentityAccess\Application\Port\SecretCipher;

/** AES-256-CBC + HMAC-SHA256 (Encrypter de Laravel, llave APP_KEY). */
final class LaravelSecretCipher implements SecretCipher
{
    public function __construct(private readonly StringEncrypter $encrypter)
    {
    }

    public function encrypt(string $plain): string
    {
        return $this->encrypter->encryptString($plain);
    }

    public function decrypt(string $cipher): string
    {
        return $this->encrypter->decryptString($cipher);
    }
}
