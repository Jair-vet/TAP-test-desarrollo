<?php

declare(strict_types=1);

namespace Tap\Shared\Application\Port;

use Tap\Shared\Application\Actor;

/**
 * Puerto de bitácora. Cualquier contexto registra aquí "qué había antes" y
 * "qué hay ahora" sin depender de cómo se guarda (lo implementa el contexto Audit).
 */
interface AuditTrail
{
    /**
     * @param array<string, mixed>|null $before Snapshot previo (null al crear)
     * @param array<string, mixed>|null $after  Snapshot nuevo (null al eliminar)
     * @param array<string, mixed>      $meta   Datos adicionales (motivo, etc.)
     */
    public function record(
        string $event,
        string $entityType,
        ?string $entityId,
        ?string $entityCode,
        Actor $actor,
        ?array $before = null,
        ?array $after = null,
        array $meta = [],
    ): void;
}
