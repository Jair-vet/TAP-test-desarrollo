<?php

declare(strict_types=1);

namespace Tap\Shared\Domain\Exception;

/** Regla de negocio violada por el estado actual (duplicados, registros en uso...). */
class Conflict extends DomainError
{
}
