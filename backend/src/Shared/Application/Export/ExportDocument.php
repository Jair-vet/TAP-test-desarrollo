<?php

declare(strict_types=1);

namespace Tap\Shared\Application\Export;

/**
 * Documento tabular agnóstico del formato: los casos de uso arman título,
 * columnas y filas; el adaptador decide si es PDF o Excel.
 */
final class ExportDocument
{
    /**
     * @param array<string, string>       $columns llave => encabezado visible
     * @param iterable<array<string, scalar|\DateTimeInterface|null>> $rows Iterable (puede ser un generador: no carga todo en memoria)
     */
    public function __construct(
        public readonly string $title,
        public readonly string $filenameBase,
        public readonly array $columns,
        public readonly iterable $rows,
        public readonly ?string $subtitle = null,
    ) {
    }
}
