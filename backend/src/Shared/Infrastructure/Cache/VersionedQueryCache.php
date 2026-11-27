<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository;
use Tap\Shared\Application\Port\QueryCache;
use Throwable;

/**
 * Adaptador Redis de {@see QueryCache}.
 *
 * Llave final: qc:{namespace}:v{version}:{sha1(params)}
 * Si Redis falla, la app sigue funcionando leyendo directo de Mongo
 * (la caché es una optimización, nunca un punto único de falla).
 */
final class VersionedQueryCache implements QueryCache
{
    public function __construct(private readonly Repository $cache)
    {
    }

    public function remember(string $namespace, array $key, int $ttlSeconds, callable $resolver): mixed
    {
        try {
            $cacheKey = sprintf('qc:%s:v%d:%s', $namespace, $this->version($namespace), sha1(serialize($key)));

            /** @phpstan-ignore-next-line argument.templateType (el tipo lo define el resolver) */
            return $this->cache->remember($cacheKey, $ttlSeconds, $resolver);
        } catch (Throwable $e) {
            report($e);

            return $resolver();
        }
    }

    public function invalidate(string ...$namespaces): void
    {
        foreach ($namespaces as $namespace) {
            try {
                $this->cache->increment($this->versionKey($namespace));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function version(string $namespace): int
    {
        $version = $this->cache->get($this->versionKey($namespace));

        if ($version === null) {
            $this->cache->forever($this->versionKey($namespace), 1);

            return 1;
        }

        return (int) $version;
    }

    private function versionKey(string $namespace): string
    {
        return "qc:{$namespace}:version";
    }
}
