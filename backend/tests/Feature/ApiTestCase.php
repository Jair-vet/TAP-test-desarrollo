<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tap\IdentityAccess\Application\Port\PasswordHasher;
use Tap\IdentityAccess\Domain\Profile\Profile;
use Tap\IdentityAccess\Domain\Profile\ProfileRepository;
use Tap\IdentityAccess\Domain\Profile\SectionAccess;
use Tap\IdentityAccess\Domain\User\User;
use Tap\IdentityAccess\Domain\User\UserRepository;
use Tap\IdentityAccess\Infrastructure\Security\JwtKeyStore;
use Tap\Shared\Application\Port\SequenceGenerator;
use Tap\Shared\Domain\ValueObject\Email;
use Tap\Shared\Domain\ValueObject\EntityCode;
use Tests\TestCase;
use Throwable;

/**
 * Base de pruebas de API contra MongoDB real (BD aislada `tap_test`, se
 * recrea en cada prueba). Si Mongo no está disponible la prueba se omite.
 */
abstract class ApiTestCase extends TestCase
{
    protected const PASSWORD = 'Prueba#2026seg';

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.jwt.keys_path' => sys_get_temp_dir() . '/tap-feature-jwt']);
        $this->app->forgetInstance(JwtKeyStore::class);
        $this->app->make(JwtKeyStore::class)->generate();

        try {
            /** @var \MongoDB\Laravel\Connection $mongo */
            $mongo = DB::connection('mongodb');
            $mongo->getDatabase()->drop();
        } catch (Throwable $e) {
            $this->markTestSkipped('MongoDB no disponible para pruebas de API: ' . $e->getMessage());
        }

        Artisan::call('migrate', ['--force' => true]);
    }

    /**
     * @param list<array{0: string, 1: list<string>}> $sections
     */
    protected function makeUser(string $email, array $sections, bool $mustChange = false): User
    {
        $now = new \DateTimeImmutable();
        $profiles = $this->app->make(ProfileRepository::class);
        $users = $this->app->make(UserRepository::class);
        $seq = $this->app->make(SequenceGenerator::class);
        $hasher = $this->app->make(PasswordHasher::class);

        $profile = Profile::create(
            $profiles->nextId(),
            EntityCode::generate('PRF', $seq->next('profiles')),
            'Perfil ' . $email,
            null,
            array_map(static fn (array $s): SectionAccess => SectionAccess::from($s[0], $s[1]), $sections),
            $now,
        );
        $profiles->save($profile);

        $user = User::register(
            $users->nextId(),
            EntityCode::generate('USR', $seq->next('users')),
            'Usuario ' . $email,
            Email::from($email),
            null,
            'photos/00/' . str_repeat('0', 32) . '.webp',
            [$profile->id()],
            $hasher->hash(self::PASSWORD),
            $now,
        );
        if (! $mustChange) {
            $user->changePassword($hasher->hash(self::PASSWORD), $now);
        }
        $users->save($user);

        return $user;
    }

    protected function admin(): User
    {
        $all = ['view', 'create', 'update', 'delete', 'export'];

        return $this->makeUser('admin@tap.test', [['products', $all], ['users', $all], ['profiles', $all], ['audit', ['view', 'export']]]);
    }

    protected function login(string $email, string $password = self::PASSWORD): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
    }

    protected function tokenFor(string $email): string
    {
        return (string) $this->login($email)->assertOk()->json('data.access_token');
    }
}
