<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use MongoDB\Database;
use MongoDB\Driver\Exception\CommandException;
use Tap\Shared\Infrastructure\Persistence\Mongo\Mongo;

/**
 * Motor de búsqueda e índices para escalar.
 *
 * 1. Buscador: el campo de texto `search` (que se consultaba con una expresión
 *    regular y obligaba a recorrer TODA la colección) se reemplaza por
 *    `search_tokens`, un índice invertido de prefijos con índice multikey.
 *    Ver Mongo::searchTokens().
 * 2. Ordenamiento: cada columna ordenable tiene un índice que coincide con el
 *    orden real ({campo, _id}); los campos únicos se ordenan con su índice único.
 *    Así ningún listado ordena en memoria (límite de 100 MB de MongoDB).
 *
 * La regla queda vigilada por tests/Feature/QueryPlanTest.php: si una consulta
 * nueva no tiene índice, la prueba (y el CI) fallan.
 */
return new class () extends Migration {
    protected $connection = 'mongodb';

    /** Colecciones con buscador y sus índices nuevos de orden. */
    private const PLAN = [
        'products' => [
            'drop' => ['name', 'price', 'brand'],
            'create' => [
                ['key' => ['name' => 1, '_id' => 1], 'name' => 'name_sort'],
                ['key' => ['brand' => 1, '_id' => 1], 'name' => 'brand_sort'],
                ['key' => ['price' => 1, '_id' => 1], 'name' => 'price_sort'],
            ],
        ],
        'users' => [
            'drop' => ['name'],
            'create' => [
                ['key' => ['name' => 1, '_id' => 1], 'name' => 'name_sort'],
            ],
        ],
        'profiles' => [
            'drop' => [],
            'create' => [],
        ],
        'audit_logs' => [
            'drop' => [],
            'create' => [
                ['key' => ['event' => 1, '_id' => 1], 'name' => 'event_sort'],
                ['key' => ['entity_type' => 1, '_id' => 1], 'name' => 'entity_type_sort'],
            ],
        ],
    ];

    public function up(): void
    {
        $db = $this->db();

        foreach (self::PLAN as $name => $indexes) {
            $this->addTokensToValidator($db, $name);
            $this->backfillTokens($db, $name);

            $collection = $db->selectCollection($name);
            foreach ($indexes['drop'] as $index) {
                $this->dropIndexIfExists($db, $name, $index);
            }
            $collection->createIndexes([
                ['key' => ['search_tokens' => 1], 'name' => 'search_tokens'],
                ...$indexes['create'],
            ]);
        }
    }

    public function down(): void
    {
        $db = $this->db();

        foreach (self::PLAN as $name => $indexes) {
            $this->dropIndexIfExists($db, $name, 'search_tokens');
            foreach ($indexes['create'] as $index) {
                $this->dropIndexIfExists($db, $name, $index['name']);
            }
            foreach ($indexes['drop'] as $field) {
                $db->selectCollection($name)->createIndex([$field => 1], ['name' => $field]);
            }
        }
    }

    /**
     * Agrega `search_tokens` (arreglo de textos) al validador $jsonSchema actual
     * y quita el campo viejo `search`. Se parte del validador vigente para no
     * perder ninguna otra regla.
     */
    private function addTokensToValidator(Database $db, string $name): void
    {
        $info = iterator_to_array($db->listCollections(['filter' => ['name' => $name]]), false)[0] ?? null;
        if ($info === null) {
            return;
        }

        $options = json_decode((string) json_encode($info->getOptions()), true);
        $schema = $options['validator']['$jsonSchema'] ?? null;
        if (! is_array($schema)) {
            return;
        }

        unset($schema['properties']['search']);
        $schema['properties']['search_tokens'] = [
            'bsonType' => 'array',
            'maxItems' => Mongo::MAX_TOKENS,
            'items' => ['bsonType' => 'string', 'maxLength' => Mongo::MAX_TOKEN_LENGTH],
        ];

        $db->command([
            'collMod' => $name,
            'validator' => ['$jsonSchema' => $schema],
            'validationLevel' => 'strict',
            'validationAction' => 'error',
        ]);
    }

    /** Convierte los documentos existentes (campo `search` → `search_tokens`) en lotes. */
    private function backfillTokens(Database $db, string $name): void
    {
        $collection = $db->selectCollection($name);
        $batch = [];

        foreach ($collection->find(['search' => ['$exists' => true]], ['projection' => ['search' => 1], 'typeMap' => ['root' => 'array']]) as $doc) {
            $batch[] = ['updateOne' => [
                ['_id' => $doc['_id']],
                ['$set' => ['search_tokens' => Mongo::searchTokens((string) $doc['search'])], '$unset' => ['search' => '']],
            ]];
            if (count($batch) === 500) {
                $collection->bulkWrite($batch, ['ordered' => false]);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $collection->bulkWrite($batch, ['ordered' => false]);
        }
    }

    private function dropIndexIfExists(Database $db, string $collection, string $index): void
    {
        try {
            $db->selectCollection($collection)->dropIndex($index);
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) { // 27 = IndexNotFound
                throw $e;
            }
        }
    }

    private function db(): Database
    {
        /** @var \MongoDB\Laravel\Connection $connection */
        $connection = DB::connection('mongodb');

        return $connection->getDatabase();
    }
};
