<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Persistence\Mongo;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;
use Tap\Shared\Domain\Exception\NotFound;
use Tap\Shared\Domain\Pagination\ListQuery;

/**
 * Utilidades de los adaptadores MongoDB.
 *
 * Aquí vive la defensa contra inyección NoSQL del lado de la BD:
 *  - Los IDs se validan como ObjectId de 24 hex ANTES de consultar.
 *  - La búsqueda de texto se escapa con preg_quote: el usuario nunca controla
 *    la expresión regular (evita ReDoS y comodines).
 *  - El ordenamiento sale de una lista blanca: no se puede ordenar por
 *    campos internos ni inyectar operadores.
 *  - Los filtros se construyen como arreglos PHP con valores tipados, nunca
 *    concatenando strings ni pasando el request crudo.
 */
final class Mongo
{
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
     * Filtro de búsqueda seguro sobre el campo normalizado `search`.
     *
     * @return array<string, mixed>
     */
    public static function searchFilter(?string $term): array
    {
        if ($term === null || trim($term) === '') {
            return [];
        }

        $normalized = self::searchable(mb_substr($term, 0, 80));

        return ['search' => new Regex(preg_quote($normalized, '/'), 'i')];
    }

    /**
     * @param array<string, string> $sortable campo público => campo en Mongo
     *
     * @return array<string, int>
     */
    public static function sort(ListQuery $query, array $sortable, string $default = 'created_at'): array
    {
        $field = $sortable[$query->sort] ?? $sortable[$default] ?? $default;
        $direction = $query->direction === 'asc' ? 1 : -1;

        // _id como desempate: paginación estable aunque haya valores iguales
        return [$field => $direction, '_id' => $direction];
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
