<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\Profile;

use Tap\IdentityAccess\Application\Authorization\AuthUserProvider;
use Tap\IdentityAccess\Domain\Exception\IdentityConflict;
use Tap\IdentityAccess\Domain\Profile\Action;
use Tap\IdentityAccess\Domain\Profile\Profile;
use Tap\IdentityAccess\Domain\Profile\ProfileRepository;
use Tap\IdentityAccess\Domain\Profile\Section;
use Tap\IdentityAccess\Domain\Profile\SectionAccess;
use Tap\IdentityAccess\Domain\User\UserRepository;
use Tap\Shared\Application\Actor;
use Tap\Shared\Application\Export\ExportDocument;
use Tap\Shared\Application\Export\ExportedFile;
use Tap\Shared\Application\Export\ExportFormat;
use Tap\Shared\Application\Port\AuditTrail;
use Tap\Shared\Application\Port\Clock;
use Tap\Shared\Application\Port\QueryCache;
use Tap\Shared\Application\Port\SequenceGenerator;
use Tap\Shared\Application\Port\TabularExporter;
use Tap\Shared\Domain\Exception\NotFound;
use Tap\Shared\Domain\Pagination\ListQuery;
use Tap\Shared\Domain\Pagination\Page;
use Tap\Shared\Domain\ValueObject\EntityCode;

/**
 * Casos de uso de perfiles de usuario (roles con secciones y acciones).
 */
final class ProfileService
{
    public const CACHE_NAMESPACE = 'profiles';
    private const CACHE_TTL = 600;

    public function __construct(
        private readonly ProfileRepository $profiles,
        private readonly UserRepository $users,
        private readonly SequenceGenerator $sequences,
        private readonly AuthUserProvider $authUsers,
        private readonly Clock $clock,
        private readonly QueryCache $cache,
        private readonly AuditTrail $audit,
        private readonly TabularExporter $exporter,
    ) {
    }

    public function create(ProfileInput $input, Actor $actor): Profile
    {
        if ($this->profiles->existsByName($input->name)) {
            throw IdentityConflict::profileNameTaken();
        }

        $profile = Profile::create(
            $this->profiles->nextId(),
            EntityCode::generate(Profile::CODE_PREFIX, $this->sequences->next('profiles')),
            $input->name,
            $input->description,
            $this->sections($input),
            $this->clock->now(),
        );

        $this->profiles->save($profile);
        $this->afterWrite();
        $this->audit->record('created', 'profile', $profile->id(), $profile->code()->value(), $actor, null, $profile->snapshot());

        return $profile;
    }

    public function update(string $id, ProfileInput $input, Actor $actor): Profile
    {
        $profile = $this->get($id);
        $before = $profile->snapshot();

        if ($this->profiles->existsByName($input->name, $profile->id())) {
            throw IdentityConflict::profileNameTaken();
        }

        $profile->update($input->name, $input->description, $this->sections($input), $this->clock->now());

        $this->profiles->save($profile);
        // Los permisos de todos los usuarios con este perfil cambian al instante
        $this->afterWrite();
        $this->audit->record('updated', 'profile', $profile->id(), $profile->code()->value(), $actor, $before, $profile->snapshot());

        return $profile;
    }

    public function delete(string $id, Actor $actor): void
    {
        $profile = $this->get($id);

        $inUse = $this->users->countWithProfile($profile->id());
        if ($inUse > 0) {
            throw IdentityConflict::profileInUse($inUse);
        }

        $this->profiles->delete($profile);
        $this->afterWrite();
        $this->audit->record('deleted', 'profile', $profile->id(), $profile->code()->value(), $actor, $profile->snapshot(), null);
    }

    public function get(string $id): Profile
    {
        return $this->profiles->find($id) ?? throw NotFound::entity('Perfil', $id);
    }

    /**
     * @return array{profile: Profile, users_count: int}
     */
    public function detail(string $id): array
    {
        $profile = $this->get($id);

        return ['profile' => $profile, 'users_count' => $this->users->countWithProfile($profile->id())];
    }

    /** @return Page<Profile> */
    public function list(ListQuery $query): Page
    {
        return $this->cache->remember(self::CACHE_NAMESPACE, $query->fingerprint(), self::CACHE_TTL, fn () => $this->profiles->paginate($query));
    }

    /**
     * Lista ligera para selects (id, código, nombre). Cacheada.
     *
     * @return list<array{id: string, code: string, name: string}>
     */
    public function options(): array
    {
        return $this->cache->remember(self::CACHE_NAMESPACE, ['options'], self::CACHE_TTL, fn (): array => array_map(
            static fn (Profile $p): array => ['id' => $p->id(), 'code' => $p->code()->value(), 'name' => $p->name()],
            $this->profiles->all(),
        ));
    }

    /**
     * Catálogo de secciones y acciones asignables (lo consume el formulario).
     *
     * @return list<array{key: string, label: string, actions: list<array{key: string, label: string}>}>
     */
    public function sectionsCatalog(): array
    {
        return array_map(static fn (Section $s): array => [
            'key' => $s->value,
            'label' => $s->label(),
            'actions' => array_map(static fn (Action $a): array => ['key' => $a->value, 'label' => $a->label()], $s->allowedActions()),
        ], Section::cases());
    }

    public function export(ListQuery $query, ExportFormat $format): ExportedFile
    {
        $rows = (function () use ($query): iterable {
            foreach ($this->profiles->cursor($query->withoutPagination()) as $profile) {
                yield [
                    'code' => $profile->code()->value(),
                    'name' => $profile->name(),
                    'sections' => implode(', ', array_map(
                        static fn (SectionAccess $a): string => $a->section->label(),
                        $profile->sections(),
                    )),
                    'created_at' => $profile->createdAt(),
                ];
            }
        })();

        return $this->exporter->export(new ExportDocument(
            title: 'Perfiles de usuario',
            filenameBase: 'perfiles',
            columns: [
                'code' => 'Código',
                'name' => 'Nombre',
                'sections' => 'Secciones',
                'created_at' => 'Fecha de creación',
            ],
            rows: $rows,
            subtitle: $query->search !== null ? "Filtro: \"{$query->search}\"" : null,
        ), $format);
    }

    /**
     * @return list<SectionAccess>
     */
    private function sections(ProfileInput $input): array
    {
        return array_map(
            static fn (array $s): SectionAccess => SectionAccess::from($s['section'], $s['actions']),
            $input->sections,
        );
    }

    private function afterWrite(): void
    {
        $this->cache->invalidate(self::CACHE_NAMESPACE, 'dashboard');
        $this->authUsers->invalidate();
    }
}
