<?php

declare(strict_types=1);

namespace Tests\Feature;

final class MailboxApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['security.mailbox.user' => 'revisor', 'security.mailbox.password' => 'Buzon#2026demo']);
    }

    public function test_credenciales_invalidas_no_dan_acceso(): void
    {
        $this->postJson('/api/v1/mailbox/session', ['user' => 'revisor', 'password' => 'otra'])
            ->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $this->postJson('/api/v1/mailbox/session', [])->assertStatus(422);
    }

    public function test_credenciales_validas_entregan_cookie_restringida_a_la_bandeja(): void
    {
        $response = $this->postJson('/api/v1/mailbox/session', ['user' => 'revisor', 'password' => 'Buzon#2026demo'])
            ->assertNoContent();

        $cookie = $response->getCookie('tap_mail', false);
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('strict', $cookie->getSameSite());
        self::assertSame('/mail/', $cookie->getPath());

        // Nginx (auth_request) consulta verify con la cookie original
        $this->withCredentials()->withUnencryptedCookie('tap_mail', (string) $cookie->getValue())
            ->get('/api/v1/mailbox/verify')->assertNoContent();
    }

    public function test_verify_rechaza_sin_cookie_o_alterada(): void
    {
        $this->get('/api/v1/mailbox/verify')->assertStatus(401);

        $value = (string) $this->postJson('/api/v1/mailbox/session', ['user' => 'revisor', 'password' => 'Buzon#2026demo'])
            ->getCookie('tap_mail', false)?->getValue();
        $forged = substr($value, 0, -1) . (str_ends_with($value, 'a') ? 'b' : 'a');

        $this->withCredentials()->withUnencryptedCookie('tap_mail', $forged)
            ->get('/api/v1/mailbox/verify')->assertStatus(401);
    }

    public function test_acceso_con_sesion_requiere_permiso_de_alta_de_usuarios(): void
    {
        $this->postJson('/api/v1/mailbox/session/me')->assertStatus(401);

        $this->makeUser('consulta@tap.test', [['products', ['view']]]);
        $this->withToken($this->tokenFor('consulta@tap.test'))->postJson('/api/v1/mailbox/session/me')->assertForbidden();

        $this->admin();
        $this->withToken($this->tokenFor('admin@tap.test'))->postJson('/api/v1/mailbox/session/me')
            ->assertNoContent()->assertCookie('tap_mail', null, false);
    }
}
