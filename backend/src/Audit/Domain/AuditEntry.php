<?php

declare(strict_types=1);

namespace Tap\Audit\Domain;

use DateTimeImmutable;

/**
 * Registro de bitácora (inmutable: la bitácora solo se agrega, nunca se edita).
 */
final class AuditEntry
{
    /**
     * @param array{id: ?string, code: ?string, name: string, email: ?string} $actor
     * @param array<string, mixed>|null                                     $before
     * @param array<string, mixed>|null                                     $after
     * @param list<array{field: string, before: mixed, after: mixed}>       $changes
     * @param array<string, mixed>                                          $meta
     */
    public function __construct(
        public readonly string $id,
        public readonly string $event,
        public readonly string $entityType,
        public readonly ?string $entityId,
        public readonly ?string $entityCode,
        public readonly array $actor,
        public readonly ?array $before,
        public readonly ?array $after,
        public readonly array $changes,
        public readonly array $meta,
        public readonly ?string $ip,
        public readonly ?string $userAgent,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
