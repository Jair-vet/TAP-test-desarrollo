<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * El límite de la API (120 peticiones/min) es POR USUARIO: si un usuario lo
 * agota, los demás usuarios de la misma red (misma IP) siguen trabajando.
 */
final class RateLimitTest extends ApiTestCase
{
    public function test_el_limite_de_peticiones_es_por_usuario_y_no_por_ip(): void
    {
        $this->admin();
        $this->makeUser('otra@tap.test', [['products', ['view']]]);
        $tokenA = $this->tokenFor('admin@tap.test');
        $tokenB = $this->tokenFor('otra@tap.test');

        for ($i = 0; $i < 120; $i++) {
            $this->withToken($tokenA)->getJson('/api/v1/auth/me')->assertOk();
        }

        $this->withToken($tokenA)->getJson('/api/v1/auth/me')->assertStatus(429)->assertJsonPath('code', 'TOO_MANY_REQUESTS');
        $this->withToken($tokenB)->getJson('/api/v1/auth/me')->assertOk();
    }
}
