<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Security;

use RuntimeException;

/**
 * Llaves Ed25519 para firmar los JWT.
 *
 *   storage/jwt/ed25519.key  (privada, 0600, NUNCA se versiona)
 *   storage/jwt/ed25519.pub  (pública)
 *
 * Se generan con `php artisan security:jwt-keys` (el entrypoint del
 * contenedor lo hace si no existen). Rotarlas = cerrar todas las sesiones.
 */
final class JwtKeyStore
{
    private ?string $private = null;
    private ?string $public = null;

    public function __construct(private readonly string $path)
    {
    }

    public function privateKey(): string
    {
        return $this->private ??= $this->read('ed25519.key');
    }

    public function publicKey(): string
    {
        return $this->public ??= $this->read('ed25519.pub');
    }

    /** Identificador de la llave (header `kid`): permite rotación sin cortar servicio. */
    public function keyId(): string
    {
        return substr(hash('sha256', $this->publicKey()), 0, 16);
    }

    /**
     * @return array{private: string, public: string}
     */
    public function generate(bool $force = false): array
    {
        if (! $force && is_file($this->path . '/ed25519.key')) {
            return ['private' => $this->path . '/ed25519.key', 'public' => $this->path . '/ed25519.pub'];
        }

        if (! is_dir($this->path) && ! mkdir($this->path, 0700, true) && ! is_dir($this->path)) {
            throw new RuntimeException("No se pudo crear {$this->path}");
        }

        $keypair = sodium_crypto_sign_keypair();
        $private = base64_encode(sodium_crypto_sign_secretkey($keypair));
        $public = base64_encode(sodium_crypto_sign_publickey($keypair));
        sodium_memzero($keypair);

        file_put_contents($this->path . '/ed25519.key', $private);
        chmod($this->path . '/ed25519.key', 0600);
        file_put_contents($this->path . '/ed25519.pub', $public);
        chmod($this->path . '/ed25519.pub', 0644);

        $this->private = $this->public = null;

        return ['private' => $this->path . '/ed25519.key', 'public' => $this->path . '/ed25519.pub'];
    }

    private function read(string $file): string
    {
        $full = $this->path . '/' . $file;
        $content = is_readable($full) ? trim((string) file_get_contents($full)) : '';

        if ($content === '') {
            throw new RuntimeException('Llaves JWT no encontradas. Ejecuta: php artisan security:jwt-keys');
        }

        return $content;
    }
}
