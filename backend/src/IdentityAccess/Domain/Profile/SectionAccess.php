<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\Profile;

use Tap\Shared\Domain\Exception\InvalidValue;

/**
 * Value Object: una sección con las acciones permitidas en ella.
 * Regla: cualquier acción implica "view" (no puedes editar lo que no ves).
 */
final class SectionAccess
{
    /**
     * @param list<Action> $actions
     */
    private function __construct(
        public readonly Section $section,
        public readonly array $actions,
    ) {
    }

    /**
     * @param list<string> $actions
     */
    public static function from(string $section, array $actions): self
    {
        $sectionEnum = Section::tryFrom($section)
            ?? throw InvalidValue::for('sections', "La sección '{$section}' no existe.");

        $allowed = array_map(static fn (Action $a): string => $a->value, $sectionEnum->allowedActions());
        $unique = array_values(array_unique($actions));

        foreach ($unique as $action) {
            if (! in_array($action, $allowed, true)) {
                throw InvalidValue::for('sections', "La acción '{$action}' no aplica a la sección {$sectionEnum->label()}.");
            }
        }

        if (! in_array(Action::View->value, $unique, true)) {
            $unique[] = Action::View->value;
        }

        // Orden canónico (el de la enum) para comparar snapshots en la bitácora
        $ordered = array_values(array_filter(
            Action::cases(),
            static fn (Action $a): bool => in_array($a->value, $unique, true),
        ));

        return new self($sectionEnum, $ordered);
    }

    /** @return list<string> */
    public function actionValues(): array
    {
        return array_map(static fn (Action $a): string => $a->value, $this->actions);
    }

    /**
     * @return array{section: string, actions: list<string>}
     */
    public function toArray(): array
    {
        return ['section' => $this->section->value, 'actions' => $this->actionValues()];
    }
}
