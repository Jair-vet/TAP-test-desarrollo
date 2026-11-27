<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Persistence\Mongo;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Tap\Shared\Domain\Exception\NotFound;
use Tap\Shared\Domain\Pagination\ListQuery;

/**
 * Utilidades de los adaptadores MongoDB.
 *
 * Aquí vive la defensa contra inyección NoSQL del lado de la BD:
 *  - Los IDs se validan como ObjectId de 24 hex ANTES de consultar.
 *  - La búsqueda NO usa expresiones regulares: el texto del usuario se
 *    convierte en palabras normalizadas que se comparan por igualdad contra un
 *    índice (sin ReDoS, sin comodines, sin operadores).
 *  - El ordenamiento sale de una lista blanca: no se puede ordenar por
 *    campos internos ni inyectar operadores.
 *  - Los filtros se construyen como arreglos PHP con valores tipados, nunca
 *    concatenando strings ni pasando el request crudo.
 */
final class Mongo
{
    /** Longitud máxima de un prefijo indexado (las palabras más largas se buscan por sus primeros 20 caracteres). */
    public const MAX_TOKEN_LENGTH = 20;

    /** Tope de tokens por documento: acota el tamaño del índice aunque un texto sea muy largo. */
    public const MAX_TOKENS = 400;

    /** Palabras máximas que se toman de lo que escribe el usuario. */
    public const MAX_QUERY_WORDS = 8;

    public static function objectId(string $id, string $entity): ObjectId
    {
        if (preg_match('/^[a-f0-9]{24}$/i', $id) !== 1) {
            throw NotFound::entity($entity, $id);
        }

        return new ObjectId($id);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<ObjectId>
     */
    public static function objectIds(array $ids): array
    {
        $valid = array_filter($ids, static fn (string $id): bool => preg_match('/^[a-f0-9]{24}$/i', $id) === 1);

        return array_values(array_map(static fn (string $id): ObjectId => new ObjectId($id), $valid));
    }

    public static function date(DateTimeInterface $date): UTCDateTime
    {
        return new UTCDateTime($date);
    }

    public static function toDate(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof UTCDateTime) {
            return DateTimeImmutable::createFromMutable($value->toDateTime())->setTimezone(new DateTimeZone('UTC'));
        }

        return null;
    }

    /**
     * Texto normalizado para búsqueda (minúsculas y sin acentos):
     * "Camión" se encuentra escribiendo "camion".
     */
    public static function searchable(string ...$parts): string
    {
        $text = mb_strtolower(implode(' ', $parts));
        // NFD separa "é" en "e" + acento; luego se eliminan las marcas diacríticas
        $decomposed = \Normalizer::normalize($text, \Normalizer::FORM_D);
        $plain = preg_replace('/\p{Mn}+/u', '', $decomposed === false ? $text : $decomposed) ?? $text;

        return trim((string) preg_replace('/\s+/u', ' ', $plain));
    }

    /**
     * Motor de búsqueda: índice invertido de prefijos.
     *
     * Cada documento guarda en `search_tokens` todos los prefijos de cada palabra
     * de sus campos buscables, ya normalizados (minúsculas, sin acentos):
     *
     *   "PRD-000038 Casco 3M"  →  p, pr, prd, 0, 00, …, 000038, c, ca, cas, casc, casco, 3, 3m
     *
     * Sobre ese arreglo hay un índice multikey, así que buscar "cas" es una
     * búsqueda por igualdad en un índice (IXSCAN), no un recorrido de la colección:
     * el tiempo depende de cuántos documentos coinciden, no de cuántos hay.
     *
     * @return list<string>
     */
    public static function searchTokens(string ...$parts): array
    {
        $tokens = [];
        foreach (self::words(self::searchable(...$parts)) as $word) {
            $max = min(mb_strlen($word), self::MAX_TOKEN_LENGTH);
            for ($length = 1; $length <= $max; $length++) {
                $tokens[mb_substr($word, 0, $length)] = true;
            }
            if (count($tokens) >= self::MAX_TOKENS) {
                break;
            }
        }

        return array_slice(array_map('strval', array_keys($tokens)), 0, self::MAX_TOKENS);
    }

    /**
     * Filtro de búsqueda: TODAS las palabras escritas deben ser prefijo de alguna
     * palabra del documento ("casco 3m" encuentra "Casco de seguridad · 3M").
     * Ignora acentos y mayúsculas; usa el índice `search_tokens`.
     *
     * @return array<string, mixed>
     */
    public static function searchFilter(?string $term): array
    {
        if ($term === null || trim($term) === '') {
            return [];
        }

        $words = array_slice(array_values(array_unique(array_map(
            static fn (string $word): string => mb_substr($word, 0, self::MAX_TOKEN_LENGTH),
            self::words(self::searchable(mb_substr($term, 0, 80))),
        ))), 0, self::MAX_QUERY_WORDS);

        return match (count($words)) {
            // Solo signos (p. ej. ".*"): no hay palabras que buscar → ningún resultado
            0 => ['search_tokens' => ['$in' => []]],
            1 => ['search_tokens' => $words[0]],
            default => ['search_tokens' => ['$all' => $words]],
        };
    }

    /**
     * @return list<string>
     */
    private static function words(string $normalized): array
    {
        return array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $normalized) ?: [],
            static fn (string $word): bool => $word !== '',
        ));
    }

    /**
     * Orden de los listados. Agrega `_id` como desempate para que la paginación
     * sea estable, salvo en campos únicos (código, correo…) donde no hace falta:
     * así cada orden coincide EXACTAMENTE con un índice y MongoDB no ordena en
     * memoria (regla verificada por tests/Feature/QueryPlanTest.php).
     *
     * @param array<string, string> $sortable campo público => campo en Mongo
     * @param list<string>          $unique   campos de Mongo con índice único
     *
     * @return array<string, int>
     */
    public static function sort(ListQuery $query, array $sortable, string $default = 'created_at', array $unique = []): array
    {
        $field = $sortable[$query->sort] ?? $sortable[$default] ?? $default;
        $direction = $query->direction === 'asc' ? 1 : -1;

        return in_array($field, $unique, true) ? [$field => $direction] : [$field => $direction, '_id' => $direction];
    }

    /**
     * @return array{skip: int, limit: int}
     */
    public static function window(ListQuery $query): array
    {
        if ($query->perPage === PHP_INT_MAX) {
            return ['skip' => 0, 'limit' => 0];
        }

        return ['skip' => $query->offset(), 'limit' => $query->perPage];
    }
}
