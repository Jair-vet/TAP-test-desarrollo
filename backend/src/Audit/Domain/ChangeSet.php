<?php

declare(strict_types=1);

namespace Tap\Audit\Domain;

/**
 * Calcula las diferencias campo por campo entre dos snapshots.
 * Es lo que permite "comparar la información anterior con la actual".
 * Función pura: fácil de probar (ver tests/Unit/Audit/ChangeSetTest).
 */
final class ChangeSet
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     *
     * @return list<array{field: string, before: mixed, after: mixed}>
     */
    public static function between(?array $before, ?array $after): array
    {
        $before = self::flatten($before ?? []);
        $after = self::flatten($after ?? []);
        $fields = array_unique([...array_keys($before), ...array_keys($after)]);
        sort($fields);

        $changes = [];
        foreach ($fields as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;
            if ($old !== $new) {
                $changes[] = ['field' => (string) $field, 'before' => $old, 'after' => $new];
            }
        }

        return $changes;
    }

    /**
     * Aplana arreglos anidados con notación de punto (sections.products).
     * Las listas se convierten en texto para compararlas como un todo.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, scalar|null>
     */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $name = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value) && ! array_is_list($value)) {
                $out += self::flatten($value, $name);
            } elseif (is_array($value)) {
                $out[$name] = implode(', ', array_map('strval', $value));
            } else {
                $out[$name] = is_scalar($value) || $value === null ? $value : (string) json_encode($value);
            }
        }

        return $out;
    }
}
