<?php

declare(strict_types=1);

namespace Tap\Shared\Application\Port;

/**
 * Caché de lecturas con invalidación por "namespace versionado".
 *
 * En vez de borrar llaves una por una, cada namespace (products, users...)
 * tiene un número de versión que forma parte de la llave. Escribir un producto
 * sube la versión de `products` y todas las páginas cacheadas quedan obsoletas
 * en O(1), sin escanear Redis.
 */
interface QueryCache
{
    /**
     * @template T
     *
     * @param array<array-key, mixed> $key
     * @param callable(): T $resolver
     *
     * @return T
     */
    public function remember(string $namespace, array $key, int $ttlSeconds, callable $resolver): mixed;

    public function invalidate(string ...$namespaces): void;
}
