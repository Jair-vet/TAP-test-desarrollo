<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tap\Catalog\Domain\Price;
use Tap\Shared\Domain\Exception\InvalidValue;

final class PriceTest extends TestCase
{
    #[DataProvider('valid')]
    public function test_acepta_maximo_tres_digitos_enteros(string|int|float $raw, string $expected): void
    {
        self::assertSame($expected, Price::from($raw)->amount());
    }

    /** @return iterable<string, array{0: string|int|float, 1: string}> */
    public static function valid(): iterable
    {
        yield 'entero' => [5, '5.00'];
        yield 'tres dígitos' => ['999', '999.00'];
        yield 'con decimales' => ['999.99', '999.99'];
        yield 'float' => [12.5, '12.50'];
        yield 'un decimal' => ['0.5', '0.50'];
    }

    #[DataProvider('invalid')]
    public function test_rechaza_precios_fuera_de_regla(string|int|float $raw): void
    {
        $this->expectException(InvalidValue::class);
        Price::from($raw);
    }

    /** @return iterable<string, array{0: string|int|float}> */
    public static function invalid(): iterable
    {
        yield 'cuatro dígitos' => ['1000'];
        yield 'tres decimales' => ['10.555'];
        yield 'cero' => ['0'];
        yield 'negativo' => ['-5'];
        yield 'texto' => ['abc'];
        yield 'vacío' => [''];
    }
}
