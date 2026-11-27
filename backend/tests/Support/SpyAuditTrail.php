<?php

declare(strict_types=1);

namespace Tests\Support;

use Tap\Shared\Application\Actor;
use Tap\Shared\Application\Port\AuditTrail;

final class SpyAuditTrail implements AuditTrail
{
    /** @var list<array<string, mixed>> */
    public array $records = [];

    public function record(string $event, string $entityType, ?string $entityId, ?string $entityCode, Actor $actor, ?array $before = null, ?array $after = null, array $meta = []): void
    {
        $this->records[] = compact('event', 'entityType', 'entityId', 'entityCode', 'before', 'after', 'meta') + ['actor' => $actor->name];
    }
}
