<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Clock;

use DateTimeImmutable;
use DateTimeZone;
use Tap\Shared\Application\Port\Clock;

/**
 * Reloj real del sistema en UTC (en las pruebas se sustituye por un reloj fijo).
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
