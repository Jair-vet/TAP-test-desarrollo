<?php

declare(strict_types=1);

namespace Tests\Unit\IdentityAccess;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tap\IdentityAccess\Domain\User\User;
use Tap\IdentityAccess\Infrastructure\Security\SecurePasswordGenerator;
use Tap\Shared\Domain\ValueObject\Email;
use Tap\Shared\Domain\ValueObject\EntityCode;

final class UserSecurityTest extends TestCase
{
    private function user(): User
    {
        return User::register('u1', EntityCode::generate('USR', 1), 'Ana', Email::from('ana@tap.test'), null, 'photos/aa/x.webp', [], 'hash', new DateTimeImmutable('2026-01-01 10:00'));
    }

    public function test_alta_obliga_a_cambiar_la_contrasena(): void
    {
        self::assertTrue($this->user()->mustChangePassword());
    }

    public function test_bloquea_tras_intentos_fallidos(): void
    {
        $user = $this->user();
        $now = new DateTimeImmutable('2026-01-01 10:00');

        for ($i = 0; $i < 4; $i++) {
            $user->registerFailedLogin($now, 5, 15);
        }
        self::assertFalse($user->isLocked($now));

        $user->registerFailedLogin($now, 5, 15);
        self::assertTrue($user->isLocked($now));
        self::assertFalse($user->isLocked($now->modify('+16 minutes')));
    }

    public function test_cambiar_contrasena_invalida_tokens_previos(): void
    {
        $user = $this->user();
        $version = $user->tokenVersion();

        $user->changePassword('nuevo-hash', new DateTimeImmutable());

        self::assertSame($version + 1, $user->tokenVersion());
        self::assertFalse($user->mustChangePassword());
    }

    public function test_contrasena_temporal_no_reemplaza_la_actual_hasta_usarse(): void
    {
        $user = $this->user();
        $now = new DateTimeImmutable();
        $user->issueTemporaryPassword('temp-hash', $now->modify('+60 minutes'), $now);

        self::assertSame('hash', $user->passwordHash());

        $user->promoteTemporaryPassword($now);
        self::assertSame('temp-hash', $user->passwordHash());
        self::assertTrue($user->mustChangePassword());
        self::assertNull($user->temporaryPassword());
    }

    public function test_snapshot_de_bitacora_nunca_incluye_secretos(): void
    {
        $snapshot = $this->user()->snapshot();

        self::assertArrayNotHasKey('password_hash', $snapshot);
        self::assertStringNotContainsString('hash', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    public function test_generador_cumple_la_politica(): void
    {
        $generator = new SecurePasswordGenerator();
        for ($i = 0; $i < 50; $i++) {
            $password = $generator->generate();
            self::assertSame(16, strlen($password));
            self::assertMatchesRegularExpression('/[A-Z]/', $password);
            self::assertMatchesRegularExpression('/[a-z]/', $password);
            self::assertMatchesRegularExpression('/\d/', $password);
            self::assertMatchesRegularExpression('/[^A-Za-z0-9]/', $password);
        }
        self::assertMatchesRegularExpression('/^[A-Z2-9]{5}-[A-Z2-9]{5}$/', $generator->recoveryCodes(1)[0]);
    }
}
