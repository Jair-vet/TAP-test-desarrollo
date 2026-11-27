<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API v1  (prefijo /api/v1, ver bootstrap/app.php)
|--------------------------------------------------------------------------
| Mapa rápido:
|   Públicas ............ /health, /auth/login, /auth/two-factor/challenge,
|                         /auth/refresh, /auth/logout, /auth/forgot-password,
|                         /media/users/{id}/photo (URL firmada),
|                         /mailbox/* (acceso a la bandeja de correos de prueba)
|   Autenticadas ........ /auth/me, /auth/password, /auth/two-factor/*
|   Autenticadas + listas + permiso por sección:
|                         /dashboard, /products, /users, /profiles, /sections,
|                         /audit-logs
|
| Para proteger una ruta nueva: ->middleware('permission:{sección},{acción}')
| Secciones: products, users, profiles, audit
| Acciones:  view, create, update, delete, export
*/

use Illuminate\Support\Facades\Route;
use MongoDB\Laravel\Connection;
use Tap\Audit\Infrastructure\Http\AuditController;
use Tap\Catalog\Infrastructure\Http\ProductController;
use Tap\IdentityAccess\Infrastructure\Http\Controllers\AuthController;
use Tap\IdentityAccess\Infrastructure\Http\Controllers\MediaController;
use Tap\IdentityAccess\Infrastructure\Http\Controllers\ProfileController;
use Tap\IdentityAccess\Infrastructure\Http\Controllers\TwoFactorController;
use Tap\IdentityAccess\Infrastructure\Http\Controllers\UserController;
use Tap\Reporting\Infrastructure\Http\DashboardController;
use Tap\Support\Infrastructure\Http\MailboxController;

$objectId = '[a-f0-9]{24}';

// ---------------------------------------------------------------- públicas
Route::get('health', static function (Connection $mongo) {
    $checks = ['mongo' => false, 'redis' => false];
    try {
        $mongo->getMongoDB()->command(['ping' => 1]);
        $checks['mongo'] = true;
    } catch (Throwable) {
    }
    try {
        $checks['redis'] = (bool) app('redis')->connection()->ping();
    } catch (Throwable) {
    }
    $ok = ! in_array(false, $checks, true);

    return response()->json(['status' => $ok ? 'ok' : 'degraded', 'checks' => $checks], $ok ? 200 : 503);
})->name('health');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::post('two-factor/challenge', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:mfa')->name('two-factor.challenge');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:refresh')->name('refresh');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:forgot')->name('forgot');
});

// Bandeja de correos de prueba (Mailpit en /mail/, protegida por Nginx con auth_request)
Route::prefix('mailbox')->name('mailbox.')->controller(MailboxController::class)->group(function () {
    Route::post('session', 'login')->middleware('throttle:mailbox')->name('login');
    Route::delete('session', 'logout')->name('logout');
    Route::get('verify', 'verify')->name('verify');
    Route::post('session/me', 'loginWithSession')
        ->middleware(['auth.jwt', 'throttle:api', 'account.ready', 'permission:users,create'])
        ->name('login-with-session');
});

Route::get('media/users/{id}/photo', [MediaController::class, 'userPhoto'])
    ->where('id', $objectId)
    ->name('media.user-photo');

// ----------------------------------------------------------- autenticadas
Route::middleware(['auth.jwt', 'throttle:api'])->group(function () use ($objectId) {

    // Cuenta propia: disponibles aunque deba cambiar contraseña / activar 2FA
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::put('password', [AuthController::class, 'changePassword'])->name('password');
        Route::get('two-factor', [TwoFactorController::class, 'status'])->name('two-factor.status');
        Route::post('two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
        Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::delete('two-factor', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
        Route::post('two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes');
    });

    // Resto del sistema: requiere cuenta "lista" (contraseña definitiva)
    Route::middleware('account.ready')->group(function () use ($objectId) {

        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // ---------------------------------------------- Productos
        Route::prefix('products')->name('products.')->controller(ProductController::class)->group(function () use ($objectId) {
            Route::get('/', 'index')->middleware('permission:products,view')->name('index');
            Route::get('export', 'export')->middleware('permission:products,export')->name('export');
            Route::post('/', 'store')->middleware('permission:products,create')->name('store');
            Route::get('{id}', 'show')->where('id', $objectId)->middleware('permission:products,view')->name('show');
            Route::put('{id}', 'update')->where('id', $objectId)->middleware('permission:products,update')->name('update');
            Route::delete('{id}', 'destroy')->where('id', $objectId)->middleware('permission:products,delete')->name('destroy');
        });

        // ---------------------------------------------- Usuarios
        Route::prefix('users')->name('users.')->controller(UserController::class)->group(function () use ($objectId) {
            Route::get('/', 'index')->middleware('permission:users,view')->name('index');
            Route::get('export', 'export')->middleware('permission:users,export')->name('export');
            Route::post('/', 'store')->middleware('permission:users,create')->name('store');
            Route::get('{id}', 'show')->where('id', $objectId)->middleware('permission:users,view')->name('show');
            Route::put('{id}', 'update')->where('id', $objectId)->middleware('permission:users,update')->name('update');
            Route::delete('{id}', 'destroy')->where('id', $objectId)->middleware('permission:users,delete')->name('destroy');
        });

        // ---------------------------------------------- Perfiles
        Route::get('sections', [ProfileController::class, 'sections'])->middleware('permission:profiles,view')->name('sections');
        Route::prefix('profiles')->name('profiles.')->controller(ProfileController::class)->group(function () use ($objectId) {
            Route::get('/', 'index')->middleware('permission:profiles,view')->name('index');
            // Los selects de "perfiles del usuario" los usa quien administra usuarios
            Route::get('options', 'options')->middleware('permission:users,view')->name('options');
            Route::get('export', 'export')->middleware('permission:profiles,export')->name('export');
            Route::post('/', 'store')->middleware('permission:profiles,create')->name('store');
            Route::get('{id}', 'show')->where('id', $objectId)->middleware('permission:profiles,view')->name('show');
            Route::put('{id}', 'update')->where('id', $objectId)->middleware('permission:profiles,update')->name('update');
            Route::delete('{id}', 'destroy')->where('id', $objectId)->middleware('permission:profiles,delete')->name('destroy');
        });

        // ---------------------------------------------- Bitácora
        Route::prefix('audit-logs')->name('audit.')->controller(AuditController::class)->group(function () use ($objectId) {
            Route::get('/', 'index')->middleware('permission:audit,view')->name('index');
            Route::get('catalog', 'catalog')->middleware('permission:audit,view')->name('catalog');
            Route::get('export', 'export')->middleware('permission:audit,export')->name('export');
            Route::get('{id}', 'show')->where('id', $objectId)->middleware('permission:audit,view')->name('show');
        });
    });
});
