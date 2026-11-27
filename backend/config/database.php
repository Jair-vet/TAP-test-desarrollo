<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Base de datos
|--------------------------------------------------------------------------
| La persistencia principal es MongoDB (NoSQL, requisito del examen) a través
| del driver oficial `mongodb/laravel-mongodb`. Redis se usa para caché,
| colas, rate limiting y la lista de revocación de JWT.
|
| No hay conexiones SQL: si mañana piden DynamoDB o Firestore, solo se cambia
| el adaptador de persistencia (ver src/<Contexto>/Infrastructure/Persistence).
*/

return [

    'default' => env('DB_CONNECTION', 'mongodb'),

    'connections' => [

        'mongodb' => [
            'driver' => 'mongodb',
            // URI completa con usuario de mínimo privilegio (readWrite sobre la BD de la app)
            'dsn' => env('MONGODB_URI', 'mongodb://127.0.0.1:27017'),
            'database' => env('MONGODB_DATABASE', 'tap'),
            'options' => [
                'appname' => 'tap-api',
                // Timeouts cortos: si Mongo no responde, fallamos rápido en lugar de colgar PHP-FPM
                'serverSelectionTimeoutMS' => 5000,
                'connectTimeoutMS' => 5000,
                'socketTimeoutMS' => 20000,
                'retryWrites' => true,
                'w' => 'majority',
            ],
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', 'tap:'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'read_timeout' => 2,
            'timeout' => 2,
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'read_timeout' => 2,
            'timeout' => 2,
        ],

    ],

];
