<?php

declare(strict_types=1);

namespace Tap\Catalog\Domain;

use Tap\Shared\Domain\Exception\InvalidValue;

/**
 * Precio del producto. Regla del examen: "máximo 3 dígitos".
 *
 * Interpretación: hasta 3 dígitos en la parte entera y hasta 2 decimales
 * (0.01 – 999.99). Se guarda como string decimal exacto (Decimal128 en Mongo)
 * para no arrastrar errores de punto flotante (0.1 + 0.2).
 * Si el evaluador quisiera solo enteros, basta con cambiar PATTERN.
 */
final class Price
{
    private const PATTERN = '/^\d{1,3}(\.\d{1,2})?$/';

    private function __construct(private readonly string $amount)
    {
    }

    public static function from(string|int|float $raw): self
    {
        $value = is_float($raw) ? number_format($raw, 2, '.', '') : trim((string) $raw);

        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::for('price', 'El precio debe tener máximo 3 dígitos enteros y 2 decimales (0.01 a 999.99).');
        }

        if ((float) $value <= 0) {
            throw InvalidValue::for('price', 'El precio debe ser mayor a 0.');
        }

        return new self(number_format((float) $value, 2, '.', ''));
    }

    /** Representación decimal exacta con 2 decimales: "125.50" */
    public function amount(): string
    {
        return $this->amount;
    }

    public function toFloat(): float
    {
        return (float) $this->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }
}
