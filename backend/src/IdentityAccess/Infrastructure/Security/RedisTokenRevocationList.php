<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Security;

use Illuminate\Contracts\Cache\Repository;
use Tap\IdentityAccess\Application\Port\TokenRevocationList;
use Tap\Shared\Application\Port\Clock;

/**
 * Denylist de `jti` en Redis con TTL = vida restante del token.
 * Redis expira la llave solo: no crece indefinidamente.
 */
final class RedisTokenRevocationList implements TokenRevocationList
{
    public function __construct(
        private readonly Repository $cache,
        private readonly Clock $clock,
    ) {
    }

    public function revoke(string $tokenId, int $expiresAt): void
    {
        $ttl = $expiresAt - $this->clock->now()->getTimestamp();
        if ($ttl > 0) {
            $this->cache->put($this->key($tokenId), 1, $ttl + 5);
        }
    }

    public function isRevoked(string $tokenId): bool
    {
        return $this->cache->has($this->key($tokenId));
    }

    private function key(string $tokenId): string
    {
        return 'jwt:revoked:' . $tokenId;
    }
}
