<?php

declare(strict_types=1);

namespace Tap\Shared\Domain\ValueObject;

use Stringable;
use Tap\Shared\Domain\Exception\InvalidValue;

/**
 * Correo normalizado (minúsculas, sin espacios).
 *
 * Rechaza caracteres de control explícitamente: mitiga el advisory
 * GHSA-5vg9-5847-vvmq (inyección CRLF en la regla `email` de Laravel < 12.60),
 * que no tiene parche en la rama 11.x.
 */
final class Email implements Stringable
{
    private function __construct(private readonly string $value)
    {
    }

    public static function from(string $raw): self
    {
        $value = mb_strtolower(trim($raw));

        if ($value === '' || mb_strlen($value) > 150) {
            throw InvalidValue::for('email', 'El correo es obligatorio y debe tener máximo 150 caracteres.');
        }

        if (preg_match('/[\x00-\x1F\x7F\s]/u', $value) === 1) {
            throw InvalidValue::for('email', 'El correo contiene caracteres no permitidos.');
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidValue::for('email', 'El correo no tiene un formato válido.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
