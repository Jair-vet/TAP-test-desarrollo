<?php

declare(strict_types=1);

namespace Tests\Support;

use Tap\Shared\Application\Export\ExportDocument;
use Tap\Shared\Application\Export\ExportedFile;
use Tap\Shared\Application\Export\ExportFormat;
use Tap\Shared\Application\Port\TabularExporter;

final class FakeExporter implements TabularExporter
{
    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public function export(ExportDocument $document, ExportFormat $format): ExportedFile
    {
        foreach ($document->rows as $row) {
            $this->rows[] = $row;
        }

        return new ExportedFile('/dev/null', $document->filenameBase . '.' . $format->value, $format->mimeType());
    }
}
