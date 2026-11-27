<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Security;

use Illuminate\Contracts\Hashing\Hasher;
use Tap\IdentityAccess\Application\Port\PasswordHasher;

/** Argon2id vía el Hasher de Laravel (config/hashing.php). */
final class LaravelPasswordHasher implements PasswordHasher
{
    public function __construct(private readonly Hasher $hasher)
    {
    }

    public function hash(string $plain): string
    {
        return $this->hasher->make($plain);
    }

    public function verify(string $plain, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        try {
            return $this->hasher->check($plain, $hash);
        } catch (\RuntimeException) {
            // Hash con otro algoritmo/corrupto: nunca autenticar
            return false;
        }
    }

    public function needsRehash(string $hash): bool
    {
        return $this->hasher->needsRehash($hash);
    }
}
