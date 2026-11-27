<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Export;

use Barryvdh\DomPDF\PDF;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\View\Factory as ViewFactory;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Tap\Shared\Application\Export\ExportDocument;
use Tap\Shared\Application\Export\ExportedFile;
use Tap\Shared\Application\Export\ExportFormat;
use Tap\Shared\Application\Port\TabularExporter;

/**
 * Adaptador de exportación.
 *  - Excel: OpenSpout escribe en streaming (memoria constante aunque sean 100k filas).
 *  - PDF: DomPDF renderiza la vista Blade `exports/table`.
 * Los archivos se generan en un temporal y el controlador los envía y borra.
 */
final class TabularFileExporter implements TabularExporter
{
    public function __construct(
        private readonly PDF $pdf,
        private readonly ViewFactory $views,
        private readonly string $timezone,
    ) {
    }

    public function export(ExportDocument $document, ExportFormat $format): ExportedFile
    {
        $generatedAt = (new DateTimeImmutable('now', new DateTimeZone($this->timezone)))->format('d/m/Y H:i');
        $filename = sprintf('%s_%s.%s', $document->filenameBase, date('Ymd_His'), $format->value);
        $path = tempnam(sys_get_temp_dir(), 'export_') ?: sys_get_temp_dir() . '/' . uniqid('export_', true);

        match ($format) {
            ExportFormat::Excel => $this->writeExcel($document, $path),
            ExportFormat::Pdf => $this->writePdf($document, $path, $generatedAt),
        };

        return new ExportedFile($path, $filename, $format->mimeType());
    }

    private function writeExcel(ExportDocument $document, string $path): void
    {
        $options = new Options();
        $options->setColumnWidth(18, 1);
        $options->setColumnWidthForRange(34, 2, max(2, count($document->columns)));

        $writer = new Writer($options);
        $writer->openToFile($path);

        $header = (new Style())
            ->setFontBold()
            ->setFontColor(Color::BLACK)
            ->setBackgroundColor('FFD400');

        $writer->getCurrentSheet()->setName(mb_substr($document->title, 0, 31));
        $writer->addRow(Row::fromValues(array_values($document->columns), $header));

        foreach ($document->rows as $row) {
            $values = [];
            foreach (array_keys($document->columns) as $key) {
                $values[] = $this->cell($row[$key] ?? null);
            }
            $writer->addRow(Row::fromValues($values));
        }

        $writer->close();
    }

    /**
     * Las fechas se muestran como DD/MM/YYYY HH:MM en la zona horaria del
     * negocio (Manzanillo), tal como pide el examen.
     */
    private function cell(mixed $value): string|int|float|bool|null
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new DateTimeZone($this->timezone))
                ->format('d/m/Y H:i');
        }

        return is_scalar($value) || $value === null ? $value : (string) json_encode($value);
    }

    private function writePdf(ExportDocument $document, string $path, string $generatedAt): void
    {
        $rows = [];
        foreach ($document->rows as $row) {
            $rows[] = array_map(fn (mixed $value): string => (string) $this->cell($value), $row);
        }

        $html = $this->views->make('exports.table', [
            'title' => $document->title,
            'subtitle' => $document->subtitle,
            'columns' => $document->columns,
            'rows' => $rows,
            'generatedAt' => $generatedAt,
        ])->render();

        $this->pdf
            // Subconjunto de fuentes: el PDF solo incluye los glifos usados (~30 KB en vez de ~900 KB)
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false, 'isPhpEnabled' => false])
            ->setPaper('letter', count($document->columns) > 4 ? 'landscape' : 'portrait')
            ->loadHTML($html)
            ->save($path);
    }
}
