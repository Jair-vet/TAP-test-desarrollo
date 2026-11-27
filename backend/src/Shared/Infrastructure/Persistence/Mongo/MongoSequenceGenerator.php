<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Persistence\Mongo;

use MongoDB\Collection;
use MongoDB\Laravel\Connection;
use MongoDB\Operation\FindOneAndUpdate;
use Tap\Shared\Application\Port\SequenceGenerator;

/**
 * Patrón "counters" de MongoDB: un documento por secuencia y un `$inc`
 * atómico con upsert. Dos altas simultáneas jamás obtienen el mismo número
 * (a diferencia de contar documentos + 1).
 */
final class MongoSequenceGenerator implements SequenceGenerator
{
    private Collection $counters;

    public function __construct(Connection $connection)
    {
        $this->counters = $connection->getCollection('counters');
    }

    public function next(string $sequence): int
    {
        /** @var array{seq: int}|object{seq: int}|null $doc */
        $doc = $this->counters->findOneAndUpdate(
            ['_id' => $sequence],
            ['$inc' => ['seq' => 1]],
            [
                'upsert' => true,
                'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
                'typeMap' => ['root' => 'array'],
            ],
        );

        return (int) ($doc['seq'] ?? 1);
    }
}
