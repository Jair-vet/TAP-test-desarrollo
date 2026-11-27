<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Domain\Profile;

use DateTimeImmutable;
use Tap\Shared\Domain\Exception\InvalidValue;
use Tap\Shared\Domain\ValueObject\EntityCode;

/**
 * Agregado Perfil: nombre + secciones con sus acciones. Un usuario puede
 * tener varios perfiles; sus permisos son la UNIÓN de todos ellos.
 */
final class Profile
{
    public const CODE_PREFIX = 'PRF';

    /**
     * @param list<SectionAccess> $sections
     */
    private function __construct(
        private readonly string $id,
        private readonly EntityCode $code,
        private string $name,
        private ?string $description,
        private array $sections,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param list<SectionAccess> $sections
     */
    public static function create(string $id, EntityCode $code, string $name, ?string $description, array $sections, DateTimeImmutable $now): self
    {
        return new self($id, $code, self::cleanName($name), self::cleanDescription($description), self::cleanSections($sections), $now, $now);
    }

    /**
     * @param list<SectionAccess> $sections
     */
    public static function restore(string $id, EntityCode $code, string $name, ?string $description, array $sections, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt): self
    {
        return new self($id, $code, $name, $description, $sections, $createdAt, $updatedAt);
    }

    /**
     * @param list<SectionAccess> $sections
     */
    public function update(string $name, ?string $description, array $sections, DateTimeImmutable $now): void
    {
        $this->name = self::cleanName($name);
        $this->description = self::cleanDescription($description);
        $this->sections = self::cleanSections($sections);
        $this->updatedAt = $now;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $sections = [];
        foreach ($this->sections as $access) {
            $sections[$access->section->value] = implode(', ', $access->actionValues());
        }

        return [
            'code' => $this->code->value(),
            'name' => $this->name,
            'description' => $this->description,
            'sections' => $sections,
        ];
    }

    public function id(): string
    {
        return $this->id;
    }

    public function code(): EntityCode
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /** @return list<SectionAccess> */
    public function sections(): array
    {
        return $this->sections;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '' || mb_strlen($name) > 60) {
            throw InvalidValue::for('name', 'El nombre del perfil es obligatorio (máximo 60 caracteres).');
        }

        return $name;
    }

    private static function cleanDescription(?string $description): ?string
    {
        $description = $description === null ? null : trim($description);

        return $description === '' ? null : mb_substr((string) $description, 0, 255);
    }

    /**
     * @param list<SectionAccess> $sections
     *
     * @return list<SectionAccess>
     */
    private static function cleanSections(array $sections): array
    {
        if ($sections === []) {
            throw InvalidValue::for('sections', 'El perfil debe tener al menos una sección.');
        }

        $bySection = [];
        foreach ($sections as $access) {
            $bySection[$access->section->value] = $access; // la última gana: sin duplicados
        }

        return array_values($bySection);
    }
}
