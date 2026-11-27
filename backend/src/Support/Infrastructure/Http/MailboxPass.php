<?php

declare(strict_types=1);

namespace Tap\Support\Infrastructure\Http;

use Tap\Shared\Application\Port\Clock;

/**
 * Pase firmado para la bandeja de correos de prueba (Mailpit).
 *
 * Valor de la cookie: "{exp}.{nonce}.{firma}" con firma = HMAC-SHA256 de
 * "mailbox|{exp}|{nonce}" usando una llave derivada (HKDF) de APP_KEY.
 * No requiere base de datos: Nginx lo valida en cada petición a /mail/
 * (auth_request) y debe ser barato.
 */
final class MailboxPass
{
    public function __construct(
        private readonly string $key,
        private readonly Clock $clock,
        private readonly int $ttl,
    ) {
    }

    /**
     * @return array{value: string, expires: int}
     */
    public function issue(): array
    {
        $expires = $this->clock->now()->getTimestamp() + $this->ttl;
        $nonce = bin2hex(random_bytes(12));

        return ['value' => "{$expires}.{$nonce}." . $this->sign($expires, $nonce), 'expires' => $expires];
    }

    public function verify(?string $value): bool
    {
        if ($value === null || preg_match('/^(\d{10})\.([a-f0-9]{24})\.([a-f0-9]{64})$/', $value, $m) !== 1) {
            return false;
        }

        [, $expires, $nonce, $signature] = $m;

        return (int) $expires > $this->clock->now()->getTimestamp()
            && hash_equals($this->sign((int) $expires, $nonce), $signature);
    }

    private function sign(int $expires, string $nonce): string
    {
        return hash_hmac('sha256', "mailbox|{$expires}|{$nonce}", $this->key);
    }
}
