<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use MongoDB\Laravel\Connection;
use PragmaRX\Google2FA\Google2FA;
use Tap\Audit\Application\AuditService;
use Tap\Audit\Domain\AuditRepository;
use Tap\Audit\Infrastructure\Persistence\MongoAuditRepository;
use Tap\Catalog\Domain\ProductRepository;
use Tap\Catalog\Infrastructure\Persistence\MongoProductRepository;
use Tap\IdentityAccess\Application\Port\CredentialsNotifier;
use Tap\IdentityAccess\Application\Port\PasswordGenerator;
use Tap\IdentityAccess\Application\Port\PasswordHasher;
use Tap\IdentityAccess\Application\Port\PhotoStorage;
use Tap\IdentityAccess\Application\Port\SecretCipher;
use Tap\IdentityAccess\Application\Port\TokenRevocationList;
use Tap\IdentityAccess\Application\Port\TokenService;
use Tap\IdentityAccess\Application\Port\TotpProvider;
use Tap\IdentityAccess\Application\SecurityPolicy;
use Tap\IdentityAccess\Domain\Profile\ProfileRepository;
use Tap\IdentityAccess\Domain\Session\RefreshTokenRepository;
use Tap\IdentityAccess\Domain\User\UserRepository;
use Tap\IdentityAccess\Infrastructure\Console\GenerateJwtKeysCommand;
use Tap\IdentityAccess\Infrastructure\Http\Middleware\EnsureAccountIsReady;
use Tap\IdentityAccess\Infrastructure\Http\RefreshCookie;
use Tap\IdentityAccess\Infrastructure\Mail\MailCredentialsNotifier;
use Tap\IdentityAccess\Infrastructure\Persistence\MongoProfileRepository;
use Tap\IdentityAccess\Infrastructure\Persistence\MongoRefreshTokenRepository;
use Tap\IdentityAccess\Infrastructure\Persistence\MongoUserRepository;
use Tap\IdentityAccess\Infrastructure\Security\EdDsaJwtTokenService;
use Tap\IdentityAccess\Infrastructure\Security\Google2faTotpProvider;
use Tap\IdentityAccess\Infrastructure\Security\JwtKeyStore;
use Tap\IdentityAccess\Infrastructure\Security\LaravelPasswordHasher;
use Tap\IdentityAccess\Infrastructure\Security\LaravelSecretCipher;
use Tap\IdentityAccess\Infrastructure\Security\RedisTokenRevocationList;
use Tap\IdentityAccess\Infrastructure\Security\SecurePasswordGenerator;
use Tap\IdentityAccess\Infrastructure\Storage\LocalPhotoStorage;
use Tap\IdentityAccess\Infrastructure\Storage\MediaUrlSigner;
use Tap\Shared\Application\Port\AuditTrail;
use Tap\Shared\Application\Port\Clock;
use Tap\Shared\Application\Port\QueryCache;
use Tap\Shared\Application\Port\SequenceGenerator;
use Tap\Shared\Application\Port\TabularExporter;
use Tap\Shared\Infrastructure\Cache\VersionedQueryCache;
use Tap\Shared\Infrastructure\Clock\SystemClock;
use Tap\Shared\Infrastructure\Export\TabularFileExporter;
use Tap\Shared\Infrastructure\Persistence\Mongo\MongoSequenceGenerator;
use Tap\Support\Infrastructure\Http\MailboxPass;

/**
 * =====================================================================
 *  COMPOSITION ROOT de la arquitectura hexagonal
 * =====================================================================
 * Aquí (y solo aquí) se decide qué ADAPTADOR implementa cada PUERTO.
 *
 * Ejemplos de "si te piden...":
 *  - Cambiar MongoDB por DynamoDB → crea DynamoProductRepository que
 *    implemente ProductRepository y cambia UNA línea abajo.
 *  - Enviar correos con SES/SendGrid → otro CredentialsNotifier (o solo
 *    cambia MAIL_MAILER en .env, el adaptador usa el Mailer de Laravel).
 *  - Guardar fotos en S3 → S3PhotoStorage implements PhotoStorage.
 *  - JWT con RS256 → otro TokenService.
 * Ni el dominio ni los casos de uso se tocan.
 */
final class HexagonalServiceProvider extends ServiceProvider
{
    /**
     * Puerto => Adaptador (sin dependencias de configuración).
     *
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        Clock::class => SystemClock::class,
        SequenceGenerator::class => MongoSequenceGenerator::class,
        QueryCache::class => VersionedQueryCache::class,
        AuditTrail::class => AuditService::class,

        // Persistencia (MongoDB)
        ProductRepository::class => MongoProductRepository::class,
        UserRepository::class => MongoUserRepository::class,
        ProfileRepository::class => MongoProfileRepository::class,
        RefreshTokenRepository::class => MongoRefreshTokenRepository::class,
        AuditRepository::class => MongoAuditRepository::class,

        // Seguridad
        PasswordHasher::class => LaravelPasswordHasher::class,
        TokenRevocationList::class => RedisTokenRevocationList::class,
        SecretCipher::class => LaravelSecretCipher::class,
        PasswordGenerator::class => SecurePasswordGenerator::class,
    ];

    public function register(): void
    {
        $this->app->singleton(Connection::class, static function (Application $app): Connection {
            /** @var Connection $connection */
            $connection = $app->make('db')->connection('mongodb');

            return $connection;
        });
        $this->app->singleton(AuditService::class);

        $this->app->singleton(SecurityPolicy::class, static fn (): SecurityPolicy => new SecurityPolicy(
            maxLoginAttempts: (int) config('security.login.max_attempts'),
            lockoutMinutes: (int) config('security.login.lockout_minutes'),
            refreshTtlSeconds: (int) config('security.refresh.ttl'),
            temporaryPasswordTtlMinutes: (int) config('security.password_reset.temporary_ttl_minutes'),
            recoveryCodesCount: (int) config('security.two_factor.recovery_codes'),
            twoFactorRequired: (bool) config('security.two_factor.required'),
        ));

        $this->app->singleton(JwtKeyStore::class, static fn (): JwtKeyStore => new JwtKeyStore((string) config('security.jwt.keys_path')));

        $this->app->singleton(TokenService::class, static fn (Application $app): TokenService => new EdDsaJwtTokenService(
            keys: $app->make(JwtKeyStore::class),
            clock: $app->make(Clock::class),
            issuer: (string) config('security.jwt.issuer'),
            audience: (string) config('security.jwt.audience'),
            accessTtl: (int) config('security.jwt.access_ttl'),
            mfaTtl: (int) config('security.jwt.mfa_ttl'),
            leeway: (int) config('security.jwt.leeway'),
        ));

        $this->app->singleton(TotpProvider::class, static fn (): TotpProvider => new Google2faTotpProvider(
            new Google2FA(),
            (string) config('security.two_factor.issuer'),
            (int) config('security.two_factor.window'),
        ));

        $this->app->singleton(PhotoStorage::class, static fn (): PhotoStorage => new LocalPhotoStorage(storage_path('app/private')));

        $this->app->singleton(MediaUrlSigner::class, static fn (Application $app): MediaUrlSigner => new MediaUrlSigner(
            // Llave derivada (HKDF) de APP_KEY: no se reutiliza la llave de cifrado tal cual
            hash_hkdf('sha256', (string) config('app.key'), 32, 'media-url-signing'),
            $app->make(Clock::class),
            (int) config('security.media.url_ttl'),
        ));

        // Pase de la bandeja de correos de prueba (llave propia derivada de APP_KEY)
        $this->app->singleton(MailboxPass::class, static fn (Application $app): MailboxPass => new MailboxPass(
            hash_hkdf('sha256', (string) config('app.key'), 32, 'mailbox-gate'),
            $app->make(Clock::class),
            (int) config('security.mailbox.ttl'),
        ));

        $this->app->singleton(CredentialsNotifier::class, static fn (Application $app): CredentialsNotifier => new MailCredentialsNotifier(
            $app->make('mailer'),
            rtrim((string) config('app.frontend_url'), '/') . '/login',
            (string) config('security.display_timezone'),
        ));

        $this->app->bind(TabularExporter::class, static fn (Application $app): TabularExporter => new TabularFileExporter(
            $app->make('dompdf.wrapper'),
            $app->make('view'),
            (string) config('security.display_timezone'),
        ));

        $this->app->singleton(RefreshCookie::class, static fn (): RefreshCookie => new RefreshCookie(
            (string) config('security.refresh.cookie'),
            (string) config('security.refresh.path'),
        ));

        $this->app->bind(EnsureAccountIsReady::class, static fn (): EnsureAccountIsReady => new EnsureAccountIsReady(
            (bool) config('security.two_factor.required'),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([GenerateJwtKeysCommand::class]);
        }
    }
}
