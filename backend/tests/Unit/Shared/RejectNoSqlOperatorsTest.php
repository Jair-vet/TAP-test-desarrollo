<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Tap\Shared\Infrastructure\Http\Middleware\RejectNoSqlOperators;

final class RejectNoSqlOperatorsTest extends TestCase
{
    private function statusFor(Request $request): int
    {
        return (new RejectNoSqlOperators())->handle($request, static fn (): Response => new Response('ok'))->getStatusCode();
    }

    public function test_bloquea_operadores_en_json(): void
    {
        $request = Request::create('/api/v1/auth/login', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"email":{"$ne":null},"password":{"$gt":""}}');

        self::assertSame(400, $this->statusFor($request));
    }

    public function test_bloquea_operadores_en_query_string(): void
    {
        self::assertSame(400, $this->statusFor(Request::create('/api/v1/products?search[$regex]=.*')));
    }

    public function test_permite_texto_con_signo_de_pesos(): void
    {
        self::assertSame(200, $this->statusFor(Request::create('/api/v1/products?search=$100')));
    }
}
