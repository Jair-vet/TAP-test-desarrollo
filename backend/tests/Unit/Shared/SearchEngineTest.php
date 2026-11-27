<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\TestCase;
use Tap\Shared\Infrastructure\Persistence\Mongo\Mongo;

/**
 * Motor de búsqueda (índice invertido de prefijos): cómo se indexa un documento
 * y cómo se traduce lo que escribe el usuario a una consulta por índice.
 */
final class SearchEngineTest extends TestCase
{
    public function test_indexa_los_prefijos_de_cada_palabra_sin_acentos_ni_mayusculas(): void
    {
        $tokens = Mongo::searchTokens('PRD-000038', 'Botas Dieléctricas', '3M');

        foreach (['p', 'pr', 'prd', '000038', 'b', 'bot', 'botas', 'diel', 'dielectricas', '3', '3m'] as $expected) {
            self::assertContains($expected, $tokens);
        }
        self::assertNotContains('Dieléctricas', $tokens);
        self::assertNotContains('electricas', $tokens, 'Solo prefijos: no indexa a mitad de palabra');
        self::assertSame(array_values(array_unique($tokens)), $tokens, 'Sin duplicados');
    }

    public function test_acota_la_longitud_de_los_prefijos_y_el_total_de_tokens(): void
    {
        $tokens = Mongo::searchTokens(str_repeat('a', 50) . ' ' . implode(' ', array_map(static fn (int $i): string => 'palabra' . $i, range(1, 200))));

        self::assertLessThanOrEqual(Mongo::MAX_TOKENS, count($tokens));
        foreach ($tokens as $token) {
            self::assertLessThanOrEqual(Mongo::MAX_TOKEN_LENGTH, mb_strlen($token));
        }
    }

    public function test_una_palabra_se_busca_por_igualdad_en_el_indice(): void
    {
        self::assertSame(['search_tokens' => 'casco'], Mongo::searchFilter('  CASCO '));
        self::assertSame(['search_tokens' => 'camion'], Mongo::searchFilter('Camión'));
    }

    public function test_varias_palabras_deben_coincidir_todas(): void
    {
        self::assertSame(['search_tokens' => ['$all' => ['casco', '3m']]], Mongo::searchFilter('casco 3M casco'));
    }

    public function test_palabras_largas_se_buscan_por_su_prefijo_indexado(): void
    {
        $filter = Mongo::searchFilter(str_repeat('x', 30));

        self::assertSame(['search_tokens' => str_repeat('x', Mongo::MAX_TOKEN_LENGTH)], $filter);
    }

    public function test_texto_vacio_no_filtra_y_solo_signos_no_devuelve_nada(): void
    {
        self::assertSame([], Mongo::searchFilter(null));
        self::assertSame([], Mongo::searchFilter('   '));
        // Sin palabras (p. ej. un intento de regex): consulta vacía por índice, nunca "todo"
        self::assertSame(['search_tokens' => ['$in' => []]], Mongo::searchFilter('.*(+)$'));
    }

    public function test_los_operadores_de_mongo_se_tratan_como_texto(): void
    {
        self::assertSame(['search_tokens' => ['$all' => ['ne', 'null']]], Mongo::searchFilter('{"$ne": null}'));
    }
}
