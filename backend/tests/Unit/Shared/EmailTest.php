<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\TestCase;
use Tap\Shared\Domain\Exception\InvalidValue;
use Tap\Shared\Domain\ValueObject\Email;
use Tap\Shared\Domain\ValueObject\EntityCode;

final class EmailTest extends TestCase
{
    public function test_normaliza_a_minusculas(): void
    {
        self::assertSame('admin@tap.test', Email::from('  Admin@TAP.test ')->value());
    }

    public function test_rechaza_inyeccion_crlf(): void
    {
        // Mitigación de GHSA-5vg9-5847-vvmq (Laravel 11 sin parche)
        $this->expectException(InvalidValue::class);
        Email::from("victima@tap.test\r\nBcc: atacante@evil.com");
    }

    public function test_rechaza_formato_invalido(): void
    {
        $this->expectException(InvalidValue::class);
        Email::from('no-es-correo');
    }

    public function test_codigo_de_entidad(): void
    {
        self::assertSame('PRD-000042', EntityCode::generate('PRD', 42)->value());
        $this->expectException(InvalidValue::class);
        EntityCode::fromString('prd-1');
    }
}
