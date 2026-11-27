<?php

declare(strict_types=1);

namespace Tap\Shared\Application\Export;

/**
 * Formatos de exportación disponibles en las tablas.
 */
enum ExportFormat: string
{
    case Pdf = 'pdf';
    case Excel = 'xlsx';

    public function mimeType(): string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Excel => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }
}
