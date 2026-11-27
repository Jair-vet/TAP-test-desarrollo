<?php

declare(strict_types=1);

namespace Tap\Shared\Infrastructure\Http\Requests;

use Illuminate\Validation\Rule;
use Tap\Shared\Application\Export\ExportFormat;

/** Igual que un listado (respeta búsqueda y orden) + ?format=pdf|xlsx */
abstract class ExportRequest extends ListRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'format' => ['required', 'string', Rule::enum(ExportFormat::class)],
        ];
    }

    public function exportFormat(): ExportFormat
    {
        return ExportFormat::from((string) $this->validated('format'));
    }
}
