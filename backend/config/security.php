<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Seguridad de la API (config propia del proyecto)
|--------------------------------------------------------------------------
| Todo lo ajustable de autenticación vive aquí y se lee por variables de
| entorno. Si te piden "que el token dure más" o "bloquear tras 3 intentos",
| este es el archivo.
*/

return [

    'jwt' => [
        // EdDSA (Ed25519): firma asimétrica. Otro servicio podría validar tokens
        // solo con la llave pública, sin poder emitirlos.
        'algorithm' => 'EdDSA',
        'keys_path' => env('JWT_KEYS_PATH', storage_path('jwt')),
        'issuer' => env('JWT_ISSUER', 'tap-api'),
        'audience' => env('JWT_AUDIENCE', 'tap-web'),
        'access_ttl' => (int) env('JWT_ACCESS_TTL', 600),           // 10 min
        'mfa_ttl' => (int) env('JWT_MFA_TTL', 300),                 // 5 min para capturar el código 2FA
        'leeway' => 5,                                              // tolerancia de reloj (segundos)
    ],

    'refresh' => [
        'ttl' => (int) env('REFRESH_TTL', 60 * 60 * 24 * 7),        // 7 días deslizantes
        'cookie' => env('REFRESH_COOKIE', 'tap_rt'),
        'path' => '/api/v1/auth',                                   // la cookie solo viaja a /auth
    ],

    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),       // intentos fallidos antes de bloquear
        'lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
    ],

    'password_reset' => [
        // La contraseña temporal NO reemplaza la actual hasta que se usa: así nadie
        // puede "bloquear" a otro usuario solo con conocer su correo.
        'temporary_ttl_minutes' => (int) env('TEMP_PASSWORD_TTL', 60),
    ],

    'two_factor' => [
        'issuer' => env('TWO_FACTOR_ISSUER', 'TAP Prueba'),
        'window' => 1,                  // ±30 s de tolerancia
        'recovery_codes' => 8,
        'required' => (bool) env('TWO_FACTOR_REQUIRED', false),
    ],

    'media' => [
        // URLs firmadas propias (HMAC) para las fotos: no usamos las de Laravel 11
        // por el advisory GHSA-crmm-hgp2-wgrp (sin parche en la rama 11.x).
        'url_ttl' => (int) env('MEDIA_URL_TTL', 3600),
        'max_photo_kb' => (int) env('MAX_PHOTO_KB', 2048),
    ],

    // Acceso a la bandeja de correos de prueba (Mailpit en /mail/). Nginx consulta
    // /api/v1/mailbox/verify (auth_request) y solo deja pasar con la cookie firmada.
    'mailbox' => [
        'user' => env('MAILPIT_UI_USER', 'revisor'),
        'password' => env('MAILPIT_UI_PASSWORD'),
        'ttl' => (int) env('MAILBOX_SESSION_TTL', 7200),   // 2 h
        'cookie' => 'tap_mail',
        'path' => '/mail/',
    ],

    // Contraseñas de los usuarios demo (database/seeders/DatabaseSeeder.php)
    'seed' => [
        'admin_password' => env('SEED_ADMIN_PASSWORD'),
        'demo_password' => env('SEED_DEMO_PASSWORD'),
    ],

    // Zona horaria con la que se muestran fechas en PDF/Excel (Manzanillo, Colima)
    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'America/Mexico_City'),

];
