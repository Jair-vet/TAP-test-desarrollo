<?php

declare(strict_types=1);

namespace Tap\Shared\Domain\Exception;

use RuntimeException;

/**
 * Error de negocio. El dominio no conoce HTTP: solo expone un código estable
 * (`errorCode`) que el adaptador HTTP traduce a un status en
 * {@see \Tap\Shared\Infrastructure\Http\ApiExceptionRenderer}.
 */
abstract class DomainError extends RuntimeException
{
    /**
     * @param array<string, mixed> $context Datos extra seguros de exponer al cliente
     */
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
