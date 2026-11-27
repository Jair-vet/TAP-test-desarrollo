<?php

declare(strict_types=1);

/*
| Cifrado (hash) de contraseñas: Argon2id, ganador de la Password Hashing
| Competition y recomendado por OWASP. Parámetros por encima del mínimo de
| OWASP (m=19 MiB, t=2, p=1).
*/

return [

    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => true,
    ],

    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 65536), // 64 MiB
        'threads' => (int) env('ARGON_THREADS', 1),
        'time' => (int) env('ARGON_TIME', 4),
        'verify' => true,
    ],

    'rehash_on_login' => true,

];
