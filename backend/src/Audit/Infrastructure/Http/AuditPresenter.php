<?php

declare(strict_types=1);

namespace Tap\Audit\Infrastructure\Http;

use DateTimeInterface;
use Tap\Audit\Application\AuditLabels;
use Tap\Audit\Domain\AuditEntry;

/**
 * Convierte un registro de bitácora en JSON para la API (incluye antes, después y cambios).
 */
final class AuditPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(AuditEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'event' => $entry->event,
            'event_label' => AuditLabels::event($entry->event),
            'entity_type' => $entry->entityType,
            'entity_label' => AuditLabels::entity($entry->entityType),
            'entity_id' => $entry->entityId,
            'entity_code' => $entry->entityCode,
            'actor' => $entry->actor,
            'changes_count' => count($entry->changes),
            'ip' => $entry->ip,
            'created_at' => $entry->createdAt->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * Detalle con "información anterior" vs "información actual".
     *
     * @return array<string, mixed>
     */
    public static function detail(AuditEntry $entry): array
    {
        return self::summary($entry) + [
            'before' => $entry->before,
            'after' => $entry->after,
            'changes' => $entry->changes,
            'meta' => $entry->meta === [] ? null : $entry->meta,
            'user_agent' => $entry->userAgent,
        ];
    }
}
