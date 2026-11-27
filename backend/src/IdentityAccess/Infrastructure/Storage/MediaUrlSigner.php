<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Storage;

use Tap\Shared\Application\Port\Clock;

/**
 * URLs firmadas (HMAC-SHA256) para servir fotos en <img src>, donde el
 * navegador no puede mandar el header Authorization.
 *
 *   /api/v1/media/users/{id}/photo?f={archivo}&exp={unix}&sig={hmac}
 *
 * La firma cubre id + archivo + expiración con una llave derivada de
 * APP_KEY. Se implementó a mano a propósito: las signed URLs de Laravel 11
 * tienen el advisory GHSA-crmm-hgp2-wgrp sin parche en la rama 11.x.
 */
final class MediaUrlSigner
{
    public function __construct(
        private readonly string $key,
        private readonly Clock $clock,
        private readonly int $ttl,
    ) {
    }

    public function userPhotoUrl(string $userId, string $photoPath): string
    {
        // Expiración "redondeada" a la hora: la URL es estable por un rato y el
        // navegador puede cachear la imagen en lugar de descargarla cada vez.
        $now = $this->clock->now()->getTimestamp();
        $exp = (intdiv($now, $this->ttl) + 2) * $this->ttl;
        $file = basename($photoPath, '.webp');

        return sprintf(
            '/api/v1/media/users/%s/photo?f=%s&exp=%d&sig=%s',
            rawurlencode($userId),
            $file,
            $exp,
            $this->sign($userId, $file, $exp),
        );
    }

    public function verify(string $userId, string $file, int $exp, string $signature): bool
    {
        if ($exp < $this->clock->now()->getTimestamp()) {
            return false;
        }

        return hash_equals($this->sign($userId, $file, $exp), $signature);
    }

    private function sign(string $userId, string $file, int $exp): string
    {
        return hash_hmac('sha256', "user-photo|{$userId}|{$file}|{$exp}", $this->key);
    }
}
