<?php

declare(strict_types=1);

namespace Tests\Support;

use Tap\Shared\Application\Port\SequenceGenerator;

final class InMemorySequence implements SequenceGenerator
{
    /** @var array<string, int> */
    private array $values = [];

    public function next(string $sequence): int
    {
        return $this->values[$sequence] = ($this->values[$sequence] ?? 0) + 1;
    }
}
