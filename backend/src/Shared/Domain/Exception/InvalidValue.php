<?php

declare(strict_types=1);

namespace Tap\Shared\Domain\Exception;

/**
 * Un Value Object rechazó un valor. Es la última línea de defensa: aunque la
 * validación HTTP falle o se omita, el dominio nunca queda en estado inválido.
 */
final class InvalidValue extends DomainError
{
    public static function for(string $field, string $message): self
    {
        return new self($message, 'INVALID_VALUE', ['field' => $field]);
    }

    public function field(): string
    {
        return (string) ($this->context()['field'] ?? '');
    }
}
