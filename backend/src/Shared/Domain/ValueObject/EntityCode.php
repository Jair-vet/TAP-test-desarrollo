<?php

declare(strict_types=1);

namespace Tap\Shared\Domain\ValueObject;

use Stringable;
use Tap\Shared\Domain\Exception\InvalidValue;

/**
 * Código legible generado automáticamente: PREFIJO-000123.
 * El número sale de un contador atómico en MongoDB (ver MongoSequenceGenerator),
 * por lo que nunca se repite aunque dos peticiones lleguen al mismo tiempo.
 */
final class EntityCode implements Stringable
{
    private function __construct(private readonly string $value)
    {
    }

    public static function generate(string $prefix, int $sequence): self
    {
        if (preg_match('/^[A-Z]{3}$/', $prefix) !== 1 || $sequence < 1) {
            throw InvalidValue::for('code', 'Prefijo o secuencia de código inválidos.');
        }

        return new self(sprintf('%s-%06d', $prefix, $sequence));
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/^[A-Z]{3}-\d{6,}$/', $value) !== 1) {
            throw InvalidValue::for('code', 'Código con formato inválido.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
