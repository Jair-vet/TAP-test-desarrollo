<?php

declare(strict_types=1);

namespace Tap\Shared\Application\Export;

/**
 * Archivo temporal generado por un exportador, listo para descargarse y borrarse.
 */
final class ExportedFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $filename,
        public readonly string $mimeType,
    ) {
    }
}
