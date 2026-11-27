<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tap\IdentityAccess\Infrastructure\Mail\CredentialsMail;

final class AuthApiTest extends ApiTestCase
{
    public function test_login_exitoso_devuelve_jwt_en_body_y_refresh_en_cookie_httponly(): void
    {
        $this->admin();

        $response = $this->login('admin@tap.test')->assertOk()
            ->assertJsonPath('data.status', 'authenticated')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.permissions.products.0', 'view');

        $cookie = $response->getCookie('tap_rt', false);
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('strict', $cookie->getSameSite());
        self::assertSame('/api/v1/auth', $cookie->getPath());
    }

    public function test_credenciales_invalidas_mensaje_generico(): void
    {
        $this->admin();

        $this->login('admin@tap.test', 'incorrecta')->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $this->login('noexiste@tap.test', 'incorrecta')->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_inyeccion_nosql_en_login_se_rechaza(): void
    {
        $this->admin();

        $this->postJson('/api/v1/auth/login', ['email' => ['$ne' => null], 'password' => ['$gt' => '']])
            ->assertStatus(400)->assertJsonPath('code', 'MALFORMED_INPUT');
        $this->postJson('/api/v1/auth/login', ['email' => ['admin@tap.test'], 'password' => 'x'])
            ->assertStatus(422);
    }

    public function test_bloqueo_tras_cinco_intentos_fallidos(): void
    {
        $this->admin();

        for ($i = 0; $i < 4; $i++) {
            $this->login('admin@tap.test', 'mala')->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
        }
        $this->login('admin@tap.test', 'mala')->assertStatus(401)->assertJsonPath('code', 'ACCOUNT_LOCKED');
    }

    public function test_rutas_protegidas_exigen_token_valido(): void
    {
        $this->getJson('/api/v1/products')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->withToken('abc.def.ghi')->getJson('/api/v1/products')->assertStatus(401)->assertJsonPath('code', 'TOKEN_INVALID');
    }

    public function test_logout_revoca_el_token(): void
    {
        $this->admin();
        $token = $this->tokenFor('admin@tap.test');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->withToken($token)->getJson('/api/v1/products')->assertStatus(401)->assertJsonPath('code', 'TOKEN_REVOKED');
    }

    public function test_refresh_rota_el_token_y_detecta_reuso(): void
    {
        $this->admin();
        $first = $this->login('admin@tap.test')->getCookie('tap_rt', false)?->getValue();

        $rotated = $this->withCredentials()->withUnencryptedCookie('tap_rt', (string) $first)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson('/api/v1/auth/refresh')->assertOk();
        self::assertNotSame($first, $rotated->getCookie('tap_rt', false)?->getValue());

        // Reusar el refresh token viejo = robo → se revoca toda la familia
        $this->withCredentials()->withUnencryptedCookie('tap_rt', (string) $first)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson('/api/v1/auth/refresh')->assertStatus(401)->assertJsonPath('code', 'REFRESH_TOKEN_REUSED');
    }

    public function test_recuperacion_valida_que_el_usuario_exista_y_envia_credenciales(): void
    {
        Mail::fake();
        $this->admin();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nadie@tap.test'])
            ->assertStatus(404)->assertJsonPath('code', 'USER_NOT_FOUND');

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'admin@tap.test'])->assertOk();
        Mail::assertQueued(CredentialsMail::class, static fn (CredentialsMail $m): bool => $m->kind === 'reset' && $m->hasTo('admin@tap.test'));
    }

    public function test_primer_ingreso_obliga_a_cambiar_contrasena(): void
    {
        $this->makeUser('nuevo@tap.test', [['products', ['view']]], mustChange: true);
        $token = $this->tokenFor('nuevo@tap.test');

        $this->withToken($token)->getJson('/api/v1/products')->assertStatus(403)->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

        $new = $this->withToken($token)->putJson('/api/v1/auth/password', [
            'current_password' => self::PASSWORD,
            'password' => 'OtraClave#2026x',
            'password_confirmation' => 'OtraClave#2026x',
        ])->assertOk()->json('data.access_token');

        $this->withToken((string) $new)->getJson('/api/v1/products')->assertOk();
    }
}
