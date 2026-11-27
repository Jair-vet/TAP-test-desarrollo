<?php

declare(strict_types=1);

namespace Tap\Shared\Domain\Exception;

/**
 * El recurso pedido no existe (el manejador global lo convierte en HTTP 404).
 */
final class NotFound extends DomainError
{
    public static function entity(string $entity, string $id): self
    {
        return new self("{$entity} no encontrado.", 'NOT_FOUND', ['entity' => $entity, 'id' => $id]);
    }
}
