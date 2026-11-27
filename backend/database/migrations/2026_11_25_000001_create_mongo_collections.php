<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use MongoDB\Database;
use MongoDB\Driver\Exception\CommandException;

/**
 * Estructura de MongoDB: colecciones con VALIDACIÓN DE ESQUEMA ($jsonSchema)
 * + índices.
 *
 * "NoSQL" no significa "sin reglas": el validador hace que Mongo rechace
 * documentos mal formados aunque alguien escriba directo a la base (otra app,
 * un script), como un CHECK/NOT NULL en SQL. Los índices únicos garantizan
 * correo y códigos únicos incluso con peticiones concurrentes.
 */
return new class () extends Migration {
    protected $connection = 'mongodb';

    public function up(): void
    {
        $db = $this->db();

        $this->collection($db, 'products', [
            'bsonType' => 'object',
            'required' => ['code', 'name', 'brand', 'price', 'created_at', 'updated_at'],
            'properties' => [
                'code' => ['bsonType' => 'string', 'pattern' => '^PRD-[0-9]{6,}$'],
                'name' => ['bsonType' => 'string', 'minLength' => 1, 'maxLength' => 120],
                'brand' => ['bsonType' => 'string', 'minLength' => 1, 'maxLength' => 80],
                'price' => ['bsonType' => 'decimal'],
                'search' => ['bsonType' => 'string'],
                'created_at' => ['bsonType' => 'date'],
                'updated_at' => ['bsonType' => 'date'],
            ],
        ], [
            ['key' => ['code' => 1], 'unique' => true, 'name' => 'uniq_code'],
            ['key' => ['created_at' => -1, '_id' => -1], 'name' => 'created_desc'],
            ['key' => ['name' => 1], 'name' => 'name'],
            ['key' => ['price' => 1], 'name' => 'price'],
            ['key' => ['brand' => 1], 'name' => 'brand'],
        ]);

        $this->collection($db, 'users', [
            'bsonType' => 'object',
            'required' => ['code', 'name', 'email', 'profile_ids', 'security', 'created_at', 'updated_at'],
            'properties' => [
                'code' => ['bsonType' => 'string', 'pattern' => '^USR-[0-9]{6,}$'],
                'name' => ['bsonType' => 'string', 'minLength' => 1, 'maxLength' => 120],
                'email' => ['bsonType' => 'string', 'maxLength' => 150, 'pattern' => '^[^@\\s]+@[^@\\s]+$'],
                'phone' => ['bsonType' => ['string', 'null'], 'pattern' => '^\\+[1-9][0-9]{7,14}$'],
                'photo_path' => ['bsonType' => ['string', 'null']],
                'profile_ids' => ['bsonType' => 'array', 'items' => ['bsonType' => 'objectId']],
                'security' => [
                    'bsonType' => 'object',
                    'required' => ['password_hash', 'token_version'],
                    'properties' => [
                        'password_hash' => ['bsonType' => 'string', 'minLength' => 20],
                        'token_version' => ['bsonType' => ['int', 'long']],
                    ],
                ],
                'created_at' => ['bsonType' => 'date'],
                'updated_at' => ['bsonType' => 'date'],
            ],
        ], [
            ['key' => ['email' => 1], 'unique' => true, 'name' => 'uniq_email'],
            ['key' => ['code' => 1], 'unique' => true, 'name' => 'uniq_code'],
            ['key' => ['profile_ids' => 1], 'name' => 'profile_ids'],
            ['key' => ['created_at' => -1, '_id' => -1], 'name' => 'created_desc'],
            ['key' => ['name' => 1], 'name' => 'name'],
        ]);

        $this->collection($db, 'profiles', [
            'bsonType' => 'object',
            'required' => ['code', 'name', 'name_key', 'sections', 'created_at', 'updated_at'],
            'properties' => [
                'code' => ['bsonType' => 'string', 'pattern' => '^PRF-[0-9]{6,}$'],
                'name' => ['bsonType' => 'string', 'minLength' => 1, 'maxLength' => 60],
                'sections' => [
                    'bsonType' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'bsonType' => 'object',
                        'required' => ['section', 'actions'],
                        'properties' => [
                            'section' => ['enum' => ['products', 'users', 'profiles', 'audit']],
                            'actions' => [
                                'bsonType' => 'array',
                                'items' => ['enum' => ['view', 'create', 'update', 'delete', 'export']],
                            ],
                        ],
                    ],
                ],
                'created_at' => ['bsonType' => 'date'],
            ],
        ], [
            ['key' => ['code' => 1], 'unique' => true, 'name' => 'uniq_code'],
            ['key' => ['name_key' => 1], 'unique' => true, 'name' => 'uniq_name'],
            ['key' => ['created_at' => -1, '_id' => -1], 'name' => 'created_desc'],
        ]);

        $this->collection($db, 'audit_logs', [
            'bsonType' => 'object',
            'required' => ['event', 'entity_type', 'actor', 'created_at'],
            'properties' => [
                'event' => ['bsonType' => 'string'],
                'entity_type' => ['bsonType' => 'string'],
                'actor' => ['bsonType' => 'object', 'required' => ['name']],
                'created_at' => ['bsonType' => 'date'],
            ],
        ], [
            ['key' => ['created_at' => -1, '_id' => -1], 'name' => 'created_desc'],
            ['key' => ['entity_type' => 1, 'entity_id' => 1, 'created_at' => -1], 'name' => 'entity_timeline'],
            ['key' => ['event' => 1, 'created_at' => -1], 'name' => 'event'],
        ]);

        $this->collection($db, 'refresh_tokens', [
            'bsonType' => 'object',
            'required' => ['user_id', 'family_id', 'token_hash', 'expires_at'],
            'properties' => [
                'user_id' => ['bsonType' => 'objectId'],
                'token_hash' => ['bsonType' => 'string', 'minLength' => 64, 'maxLength' => 64],
                'expires_at' => ['bsonType' => 'date'],
            ],
        ], [
            ['key' => ['token_hash' => 1], 'unique' => true, 'name' => 'uniq_hash'],
            ['key' => ['family_id' => 1], 'name' => 'family'],
            ['key' => ['user_id' => 1], 'name' => 'user'],
            // TTL: Mongo elimina solo los tokens vencidos
            ['key' => ['expires_at' => 1], 'expireAfterSeconds' => 0, 'name' => 'ttl_expires'],
        ]);

        $this->collection($db, 'counters', null, []);
    }

    public function down(): void
    {
        $db = $this->db();
        foreach (['products', 'users', 'profiles', 'audit_logs', 'refresh_tokens', 'counters'] as $name) {
            $db->dropCollection($name);
        }
    }

    /**
     * Crea la colección (o actualiza su validador si ya existe) y sus índices.
     *
     * @param array<string, mixed>|null $schema
     * @param list<array<string, mixed>> $indexes
     */
    private function collection(Database $db, string $name, ?array $schema, array $indexes): void
    {
        $options = $schema === null ? [] : [
            'validator' => ['$jsonSchema' => $schema],
            'validationLevel' => 'strict',
            'validationAction' => 'error',
        ];

        try {
            $db->createCollection($name, $options);
        } catch (CommandException $e) {
            if ($e->getCode() !== 48) { // 48 = NamespaceExists
                throw $e;
            }
            if ($options !== []) {
                $db->command(['collMod' => $name] + $options);
            }
        }

        if ($indexes !== []) {
            $db->selectCollection($name)->createIndexes($indexes);
        }
    }

    private function db(): Database
    {
        /** @var \MongoDB\Laravel\Connection $connection */
        $connection = DB::connection('mongodb');

        return $connection->getDatabase();
    }
};
