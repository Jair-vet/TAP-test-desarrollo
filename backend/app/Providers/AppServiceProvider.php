<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Configuración transversal de la app: rate limiting.
 * El cableado de la arquitectura hexagonal vive en HexagonalServiceProvider.
 */
final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Límites por ruta (almacenados en Redis):
     *  - login:  5/min por correo+IP y 20/min por IP (fuerza bruta / credential stuffing)
     *  - mfa:    5/min por IP (adivinar códigos de 6 dígitos)
     *  - forgot: 3 cada 15 min por correo y 10/h por IP (enumeración/spam de correos)
     *  - api:    120/min por usuario autenticado
 *  - mailbox: 5/min por IP (acceso a la bandeja de correos de prueba)
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', static function (Request $request): array {
            $email = self::emailKey($request);

            return [
                Limit::perMinute(5)->by('login:' . sha1($email . '|' . $request->ip())),
                Limit::perMinute(20)->by('login-ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('mfa', static fn (Request $request): Limit => Limit::perMinute(5)->by('mfa:' . $request->ip()));

        RateLimiter::for('forgot', static function (Request $request): array {
            $email = self::emailKey($request);

            return [
                Limit::perMinutes(15, 3)->by('forgot:' . sha1($email)),
                Limit::perHour(10)->by('forgot-ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('refresh', static fn (Request $request): Limit => Limit::perMinute(30)->by('refresh:' . $request->ip()));

        // Bandeja de correos de prueba: 5 intentos por minuto por IP
        RateLimiter::for('mailbox', static fn (Request $request): Limit => Limit::perMinute(5)->by('mailbox:' . $request->ip()));

        RateLimiter::for('api', static function (Request $request): Limit {
            $user = $request->attributes->get('auth_user');
            $key = is_object($user) && property_exists($user, 'id') ? 'u:' . $user->id : 'ip:' . $request->ip();

            return Limit::perMinute(120)->by('api:' . $key);
        });
    }

    /** El correo puede llegar con tipo inválido (arreglo): la validación lo rechaza después. */
    private static function emailKey(Request $request): string
    {
        $email = $request->input('email');

        return is_string($email) ? Str::lower(trim($email)) : '';
    }
}
