<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Observability;

use MongoDB\Driver\Monitoring\CommandFailedEvent;
use MongoDB\Driver\Monitoring\CommandStartedEvent;
use MongoDB\Driver\Monitoring\CommandSubscriber;
use MongoDB\Driver\Monitoring\CommandSucceededEvent;

/**
 * Métrica de base de datos por petición: cuántos comandos se mandaron a MongoDB
 * y cuánto tardaron en total.
 *
 * Se engancha al driver oficial mediante su API de monitoreo (APM), así mide
 * TODAS las consultas de los repositorios sin tocarlos. La lee el middleware
 * RecordRequestMetrics para la cabecera Server-Timing y el log de peticiones lentas.
 */
final class MongoCommandMetrics implements CommandSubscriber
{
    private int $commands = 0;

    private int $micros = 0;

    public function reset(): void
    {
        $this->commands = 0;
        $this->micros = 0;
    }

    public function commandStarted(CommandStartedEvent $event): void
    {
    }

    public function commandSucceeded(CommandSucceededEvent $event): void
    {
        $this->record($event->getDurationMicros());
    }

    public function commandFailed(CommandFailedEvent $event): void
    {
        $this->record($event->getDurationMicros());
    }

    /** Número de comandos enviados a MongoDB desde el último reset(). */
    public function count(): int
    {
        return $this->commands;
    }

    /** Tiempo total en MongoDB (ms) desde el último reset(). */
    public function milliseconds(): float
    {
        return $this->micros / 1000;
    }

    private function record(int $micros): void
    {
        $this->commands++;
        $this->micros += $micros;
    }
}
