<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\User;

use Tap\Shared\Domain\Exception\InvalidValue;

/**
 * Teléfono en formato internacional E.164 (debe incluir código de país):
 * +523141234567. La validación por país (longitudes reales) la hace la capa
 * HTTP con libphonenumber; aquí se garantiza el formato canónico.
 */
final class PhoneNumber
{
    private function __construct(private readonly string $value)
    {
    }

    public static function from(string $raw): self
    {
        $value = preg_replace('/[\s\-().]/', '', trim($raw)) ?? '';

        if (preg_match('/^\+[1-9]\d{7,14}$/', $value) !== 1) {
            throw InvalidValue::for('phone', 'El teléfono debe incluir código de país, ej. +52 314 123 4567.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
