<?php

declare(strict_types=1);

namespace Tests\Support;

use Tap\Shared\Application\Port\QueryCache;

final class PassThroughCache implements QueryCache
{
    /** @var list<string> */
    public array $invalidated = [];

    public function remember(string $namespace, array $key, int $ttlSeconds, callable $resolver): mixed
    {
        return $resolver();
    }

    public function invalidate(string ...$namespaces): void
    {
        array_push($this->invalidated, ...$namespaces);
    }
}
