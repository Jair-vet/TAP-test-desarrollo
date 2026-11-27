<?php

declare(strict_types=1);

namespace Tap\Shared\Application\Port;

/** Genera números consecutivos atómicos por secuencia (products, users, profiles). */
interface SequenceGenerator
{
    public function next(string $sequence): int;
}
