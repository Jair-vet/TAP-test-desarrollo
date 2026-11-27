<?php

declare(strict_types=1);

namespace Tap\Audit\Infrastructure\Http;

use Illuminate\Validation\Rule;
use Tap\Audit\Application\AuditLabels;
use Tap\Shared\Application\Export\ExportFormat;
use Tap\Shared\Infrastructure\Http\Requests\ListRequest;

/** ?search=&entity_type=product&event=updated&entity_id=...&page=... */
final class AuditListRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['created_at', 'event', 'entity_type'];
    }

    protected function filterRules(): array
    {
        return [
            'entity_type' => ['nullable', 'string', Rule::in(array_keys(AuditLabels::ENTITIES))],
            'event' => ['nullable', 'string', Rule::in(array_keys(AuditLabels::EVENTS))],
            'entity_id' => ['nullable', 'string', 'regex:/^[a-f0-9]{24}$/i'],
        ];
    }

    public function rules(): array
    {
        $rules = parent::rules();
        if ($this->routeIs('audit.export')) {
            $rules['format'] = ['required', 'string', Rule::enum(ExportFormat::class)];
        }

        return $rules;
    }

    public function exportFormat(): ExportFormat
    {
        return ExportFormat::from((string) $this->validated('format'));
    }
}
