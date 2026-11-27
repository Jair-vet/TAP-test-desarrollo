<?php

declare(strict_types=1);

namespace Tap\Audit\Domain;

use Tap\Shared\Domain\Pagination\ListQuery;
use Tap\Shared\Domain\Pagination\Page;

/**
 * Puerto de persistencia de la bitácora (implementado por MongoAuditRepository).
 * Solo agrega y consulta: un registro de auditoría nunca se edita ni se borra.
 */
interface AuditRepository
{
    public function nextId(): string;

    public function append(AuditEntry $entry): void;

    public function find(string $id): ?AuditEntry;

    /** @return Page<AuditEntry> */
    public function paginate(ListQuery $query): Page;

    /** @return iterable<AuditEntry> */
    public function cursor(ListQuery $query): iterable;

    /**
     * @param list<string>|null $entityTypes
     *
     * @return list<AuditEntry>
     */
    public function latest(int $limit, ?array $entityTypes = null): array;
}
