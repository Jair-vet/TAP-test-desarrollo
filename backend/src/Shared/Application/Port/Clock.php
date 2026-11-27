<?php

declare(strict_types=1);

namespace Tap\Shared\Application\Port;

use DateTimeImmutable;

/** Puerto de tiempo: permite congelar la hora en las pruebas. */
interface Clock
{
    public function now(): DateTimeImmutable;
}
