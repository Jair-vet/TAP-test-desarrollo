<?php

declare(strict_types=1);

namespace Tap\Audit\Infrastructure\Persistence;

use DateTimeImmutable;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Laravel\Connection;
use Tap\Audit\Domain\AuditEntry;
use Tap\Audit\Domain\AuditRepository;
use Tap\Shared\Domain\Pagination\ListQuery;
use Tap\Shared\Domain\Pagination\Page;
use Tap\Shared\Infrastructure\Persistence\Mongo\Mongo;

/**
 * Bitácora en MongoDB: colección `audit_logs` de solo-inserción.
 * El esquema flexible de Mongo es ideal aquí: before/after guardan el
 * snapshot de entidades con formas distintas (producto, usuario, perfil)
 * en la misma colección, sin tablas por entidad.
 */
final class MongoAuditRepository implements AuditRepository
{
    private const SORTABLE = ['created_at' => 'created_at', 'event' => 'event', 'entity_type' => 'entity_type'];

    private Collection $collection;

    public function __construct(Connection $connection)
    {
        $this->collection = $connection->getCollection('audit_logs');
    }

    public function nextId(): string
    {
        return (string) new ObjectId();
    }

    public function append(AuditEntry $entry): void
    {
        $this->collection->insertOne([
            '_id' => new ObjectId($entry->id),
            'event' => $entry->event,
            'entity_type' => $entry->entityType,
            'entity_id' => $entry->entityId,
            'entity_code' => $entry->entityCode,
            'actor' => $entry->actor,
            'before' => $entry->before,
            'after' => $entry->after,
            'changes' => $entry->changes,
            'meta' => $entry->meta === [] ? (object) [] : $entry->meta,
            'ip' => $entry->ip,
            'user_agent' => $entry->userAgent,
            'search' => Mongo::searchable(
                (string) $entry->entityCode,
                $entry->actor['name'],
                (string) $entry->actor['email'],
                $entry->event,
            ),
            'created_at' => Mongo::date($entry->createdAt),
        ]);
    }

    public function find(string $id): ?AuditEntry
    {
        $doc = $this->collection->findOne(['_id' => Mongo::objectId($id, 'Registro de bitácora')], ['typeMap' => self::typeMap()]);

        return is_array($doc) ? $this->hydrate($doc) : null;
    }

    public function paginate(ListQuery $query): Page
    {
        $filter = $this->filter($query);
        $window = Mongo::window($query);

        $items = [];
        foreach ($this->collection->find($filter, [
            'sort' => Mongo::sort($query, self::SORTABLE),
            'skip' => $window['skip'],
            'limit' => $window['limit'],
            // El listado no necesita los snapshots completos (pueden ser grandes)
            'projection' => ['before' => 0, 'after' => 0, 'search' => 0, 'user_agent' => 0],
            'typeMap' => self::typeMap(),
        ]) as $doc) {
            $items[] = $this->hydrate($doc);
        }

        $total = $filter === [] ? $this->collection->estimatedDocumentCount() : $this->collection->countDocuments($filter);

        return new Page($items, $total, $query->page, $query->perPage);
    }

    public function cursor(ListQuery $query): iterable
    {
        foreach ($this->collection->find($this->filter($query), [
            'sort' => Mongo::sort($query, self::SORTABLE),
            'projection' => ['before' => 0, 'after' => 0, 'search' => 0],
            'typeMap' => self::typeMap(),
            'batchSize' => 500,
        ]) as $doc) {
            yield $this->hydrate($doc);
        }
    }

    /**
     * @param list<string>|null $entityTypes
     *
     * @return list<AuditEntry>
     */
    public function latest(int $limit, ?array $entityTypes = null): array
    {
        $filter = $entityTypes === null ? [] : ['entity_type' => ['$in' => $entityTypes]];

        $items = [];
        foreach ($this->collection->find($filter, [
            'sort' => ['created_at' => -1, '_id' => -1],
            'limit' => $limit,
            'projection' => ['before' => 0, 'after' => 0, 'search' => 0],
            'typeMap' => self::typeMap(),
        ]) as $doc) {
            $items[] = $this->hydrate($doc);
        }

        return $items;
    }

    /**
     * Filtros exactos permitidos (ya validados con lista blanca en el request).
     *
     * @return array<string, mixed>
     */
    private function filter(ListQuery $query): array
    {
        $filter = Mongo::searchFilter($query->search);

        foreach (['entity_type', 'event', 'entity_id'] as $field) {
            if (isset($query->filters[$field])) {
                $filter[$field] = (string) $query->filters[$field];
            }
        }

        return $filter;
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function hydrate(array $doc): AuditEntry
    {
        /** @var array{id: ?string, code: ?string, name: string, email: ?string} $actor */
        $actor = (array) ($doc['actor'] ?? []) + ['id' => null, 'code' => null, 'name' => 'Sistema', 'email' => null];

        return new AuditEntry(
            id: (string) $doc['_id'],
            event: (string) $doc['event'],
            entityType: (string) $doc['entity_type'],
            entityId: isset($doc['entity_id']) ? (string) $doc['entity_id'] : null,
            entityCode: isset($doc['entity_code']) ? (string) $doc['entity_code'] : null,
            actor: $actor,
            before: isset($doc['before']) && is_array($doc['before']) ? $doc['before'] : null,
            after: isset($doc['after']) && is_array($doc['after']) ? $doc['after'] : null,
            changes: array_values((array) ($doc['changes'] ?? [])),
            meta: (array) ($doc['meta'] ?? []),
            ip: isset($doc['ip']) ? (string) $doc['ip'] : null,
            userAgent: isset($doc['user_agent']) ? (string) $doc['user_agent'] : null,
            createdAt: Mongo::toDate($doc['created_at']) ?? new DateTimeImmutable(),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function typeMap(): array
    {
        return ['root' => 'array', 'document' => 'array', 'array' => 'array'];
    }
}
