<?php

declare(strict_types=1);

namespace Tap\Audit\Application;

use Tap\Audit\Domain\AuditEntry;
use Tap\Audit\Domain\AuditRepository;
use Tap\Audit\Domain\ChangeSet;
use Tap\Shared\Application\Actor;
use Tap\Shared\Application\Export\ExportDocument;
use Tap\Shared\Application\Export\ExportedFile;
use Tap\Shared\Application\Export\ExportFormat;
use Tap\Shared\Application\Port\AuditTrail;
use Tap\Shared\Application\Port\Clock;
use Tap\Shared\Application\Port\QueryCache;
use Tap\Shared\Application\Port\TabularExporter;
use Tap\Shared\Domain\Exception\NotFound;
use Tap\Shared\Domain\Pagination\ListQuery;
use Tap\Shared\Domain\Pagination\Page;
use Throwable;

/**
 * Bitácora: implementa el puerto AuditTrail que usan todos los contextos.
 *
 * Cada registro guarda:
 *   before  → cómo estaba el registro (información anterior)
 *   after   → cómo quedó (información actual)
 *   changes → diferencias campo por campo, ya calculadas para mostrarlas
 */
final class AuditService implements AuditTrail
{
    public const CACHE_NAMESPACE = 'audit';

    public function __construct(
        private readonly AuditRepository $entries,
        private readonly Clock $clock,
        private readonly QueryCache $cache,
        private readonly TabularExporter $exporter,
    ) {
    }

    public function record(
        string $event,
        string $entityType,
        ?string $entityId,
        ?string $entityCode,
        Actor $actor,
        ?array $before = null,
        ?array $after = null,
        array $meta = [],
    ): void {
        try {
            $this->entries->append(new AuditEntry(
                id: $this->entries->nextId(),
                event: $event,
                entityType: $entityType,
                entityId: $entityId,
                entityCode: $entityCode,
                actor: $actor->toArray(),
                before: $before,
                after: $after,
                changes: ChangeSet::between($before, $after),
                meta: $meta,
                ip: $actor->ip,
                userAgent: $actor->userAgent,
                createdAt: $this->clock->now(),
            ));
            $this->cache->invalidate(self::CACHE_NAMESPACE, 'dashboard');
        } catch (Throwable $e) {
            // La bitácora nunca debe tumbar la operación de negocio, pero sí se reporta
            report($e);
        }
    }

    /** @return Page<AuditEntry> */
    public function list(ListQuery $query): Page
    {
        return $this->cache->remember(self::CACHE_NAMESPACE, $query->fingerprint(), 60, fn () => $this->entries->paginate($query));
    }

    public function get(string $id): AuditEntry
    {
        return $this->entries->find($id) ?? throw NotFound::entity('Registro de bitácora', $id);
    }

    /**
     * @param list<string>|null $entityTypes
     *
     * @return list<AuditEntry>
     */
    public function latest(int $limit, ?array $entityTypes = null): array
    {
        return $this->entries->latest($limit, $entityTypes);
    }

    public function export(ListQuery $query, ExportFormat $format): ExportedFile
    {
        $rows = (function () use ($query): iterable {
            foreach ($this->entries->cursor($query->withoutPagination()) as $entry) {
                yield [
                    'created_at' => $entry->createdAt,
                    'event' => AuditLabels::event($entry->event),
                    'entity' => AuditLabels::entity($entry->entityType) . ($entry->entityCode ? " {$entry->entityCode}" : ''),
                    'actor' => $entry->actor['name'] . ($entry->actor['email'] ? " ({$entry->actor['email']})" : ''),
                    'changes' => implode('; ', array_map(
                        static fn (array $c): string => sprintf('%s: %s → %s', $c['field'], self::text($c['before']), self::text($c['after'])),
                        $entry->changes,
                    )) ?: '—',
                    'ip' => $entry->ip ?? '—',
                ];
            }
        })();

        return $this->exporter->export(new ExportDocument(
            title: 'Bitácora de cambios',
            filenameBase: 'bitacora',
            columns: [
                'created_at' => 'Fecha',
                'event' => 'Evento',
                'entity' => 'Registro',
                'actor' => 'Usuario',
                'changes' => 'Cambios (antes → después)',
                'ip' => 'IP',
            ],
            rows: $rows,
        ), $format);
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            $value === null => '∅',
            is_bool($value) => $value ? 'sí' : 'no',
            default => (string) $value,
        };
    }
}
