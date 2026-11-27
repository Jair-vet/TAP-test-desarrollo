<?php

declare(strict_types=1);

namespace Tap\Shared\Application\Port;

use Tap\Shared\Application\Export\ExportDocument;
use Tap\Shared\Application\Export\ExportedFile;
use Tap\Shared\Application\Export\ExportFormat;

/** Genera el archivo descargable (PDF o Excel) de un listado. */
interface TabularExporter
{
    public function export(ExportDocument $document, ExportFormat $format): ExportedFile;
}
