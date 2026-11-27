<?php

declare(strict_types=1);

namespace Tests\Unit\IdentityAccess;

use DateTimeImmutable;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Tap\IdentityAccess\Application\Port\TokenService;
use Tap\IdentityAccess\Domain\Exception\AuthenticationError;
use Tap\IdentityAccess\Domain\User\User;
use Tap\IdentityAccess\Infrastructure\Security\EdDsaJwtTokenService;
use Tap\IdentityAccess\Infrastructure\Security\JwtKeyStore;
use Tap\Shared\Domain\ValueObject\Email;
use Tap\Shared\Domain\ValueObject\EntityCode;
use Tests\Support\FixedClock;

final class JwtTokenServiceTest extends TestCase
{
    private FixedClock $clock;
    private EdDsaJwtTokenService $tokens;
    private User $user;

    protected function setUp(): void
    {
        $keys = new JwtKeyStore(sys_get_temp_dir() . '/tap-jwt-test-' . getmypid());
        $keys->generate(true);
        $this->clock = new FixedClock(new DateTimeImmutable());
        $this->tokens = new EdDsaJwtTokenService($keys, $this->clock, 'tap-api', 'tap-web', 600, 300, 0);
        $this->user = User::register('6b0733db25a78435670588b6', EntityCode::generate('USR', 1), 'Ana', Email::from('ana@tap.test'), null, 'photos/aa/x.webp', [], 'h', new DateTimeImmutable());
    }

    public function test_emite_y_valida_token_de_acceso(): void
    {
        $issued = $this->tokens->issue($this->user, TokenService::TYPE_ACCESS);
        $claims = $this->tokens->decode($issued->token, TokenService::TYPE_ACCESS);

        self::assertSame($this->user->id(), $claims->subject);
        self::assertSame($issued->id, $claims->tokenId);
        self::assertSame($this->user->tokenVersion(), $claims->tokenVersion);
    }

    public function test_token_mfa_no_sirve_como_token_de_acceso(): void
    {
        $mfa = $this->tokens->issue($this->user, TokenService::TYPE_MFA);

        $this->expectException(AuthenticationError::class);
        $this->tokens->decode($mfa->token, TokenService::TYPE_ACCESS);
    }

    public function test_token_alterado_se_rechaza(): void
    {
        [$header, $payload, $signature] = explode('.', $this->tokens->issue($this->user, TokenService::TYPE_ACCESS)->token);
        $claims = json_decode(JWT::urlsafeB64Decode($payload), true);
        $claims['sub'] = 'ffffffffffffffffffffffff'; // intenta suplantar a otro usuario
        $forged = $header . '.' . JWT::urlsafeB64Encode((string) json_encode($claims)) . '.' . $signature;

        $this->expectException(AuthenticationError::class);
        $this->tokens->decode($forged, TokenService::TYPE_ACCESS);
    }

    public function test_alg_none_se_rechaza(): void
    {
        $none = JWT::urlsafeB64Encode('{"alg":"none","typ":"JWT"}') . '.' . JWT::urlsafeB64Encode(json_encode([
            'iss' => 'tap-api', 'aud' => 'tap-web', 'sub' => 'x', 'jti' => 'y', 'typ' => 'access', 'tv' => 1, 'exp' => time() + 60,
        ], JSON_THROW_ON_ERROR)) . '.';

        $this->expectException(AuthenticationError::class);
        $this->tokens->decode($none, TokenService::TYPE_ACCESS);
    }

    public function test_token_expirado(): void
    {
        $issued = $this->tokens->issue($this->user, TokenService::TYPE_ACCESS);
        $this->clock->now = $this->clock->now->modify('+11 minutes');

        try {
            $this->tokens->decode($issued->token, TokenService::TYPE_ACCESS);
            self::fail('Debió expirar');
        } catch (AuthenticationError $e) {
            self::assertSame('TOKEN_EXPIRED', $e->errorCode());
        }
    }
}
