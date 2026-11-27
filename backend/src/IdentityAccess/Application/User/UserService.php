<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Application\User;

use Tap\IdentityAccess\Application\Authorization\AuthUserProvider;
use Tap\IdentityAccess\Application\Port\CredentialsNotifier;
use Tap\IdentityAccess\Application\Port\PasswordGenerator;
use Tap\IdentityAccess\Application\Port\PasswordHasher;
use Tap\IdentityAccess\Application\Port\PhotoStorage;
use Tap\IdentityAccess\Domain\Exception\IdentityConflict;
use Tap\IdentityAccess\Domain\Profile\ProfileRepository;
use Tap\IdentityAccess\Domain\Session\RefreshTokenRepository;
use Tap\IdentityAccess\Domain\User\PhoneNumber;
use Tap\IdentityAccess\Domain\User\User;
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
use Tap\Shared\Domain\Exception\InvalidValue;
use Tap\Shared\Domain\Exception\NotFound;
use Tap\Shared\Domain\Pagination\ListQuery;
use Tap\Shared\Domain\Pagination\Page;
use Tap\Shared\Domain\ValueObject\Email;
use Tap\Shared\Domain\ValueObject\EntityCode;
use Throwable;

/**
 * Casos de uso de usuarios: alta (con envío de credenciales), edición,
 * eliminación, detalle, listado y exportación.
 */
final class UserService
{
    public const CACHE_NAMESPACE = 'users';
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly UserRepository $users,
        private readonly ProfileRepository $profiles,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly PasswordHasher $hasher,
        private readonly PasswordGenerator $generator,
        private readonly CredentialsNotifier $notifier,
        private readonly PhotoStorage $photos,
        private readonly SequenceGenerator $sequences,
        private readonly AuthUserProvider $authUsers,
        private readonly Clock $clock,
        private readonly QueryCache $cache,
        private readonly AuditTrail $audit,
        private readonly TabularExporter $exporter,
    ) {
    }

    /**
     * Alta de usuario: código automático, contraseña aleatoria enviada por
     * correo y cambio obligatorio en el primer ingreso.
     */
    public function create(UserInput $input, string $photoTempPath, Actor $actor): User
    {
        $email = Email::from($input->email);
        if ($this->users->existsByEmail($email)) {
            throw IdentityConflict::emailTaken();
        }
        $this->assertProfilesExist($input->profileIds);

        $photoPath = $this->photos->store($photoTempPath);
        $plainPassword = $this->generator->generate();

        try {
            $user = User::register(
                $this->users->nextId(),
                EntityCode::generate(User::CODE_PREFIX, $this->sequences->next('users')),
                $input->name,
                $email,
                $input->phone !== null ? PhoneNumber::from($input->phone) : null,
                $photoPath,
                $input->profileIds,
                $this->hasher->hash($plainPassword),
                $this->clock->now(),
            );

            $this->users->save($user);
        } catch (Throwable $e) {
            $this->photos->delete($photoPath); // sin huérfanos si algo falla

            throw $e;
        }

        $this->notifier->sendWelcome($user, $plainPassword);
        $this->afterWrite();
        $this->audit->record('created', 'user', $user->id(), $user->code()->value(), $actor, null, $user->snapshot());

        return $user;
    }

    public function update(string $id, UserInput $input, ?string $photoTempPath, Actor $actor): User
    {
        $user = $this->get($id);
        $before = $user->snapshot();

        $email = Email::from($input->email);
        if ($this->users->existsByEmail($email, $user->id())) {
            throw IdentityConflict::emailTaken();
        }
        $this->assertProfilesExist($input->profileIds);

        $now = $this->clock->now();
        $user->updateDetails(
            $input->name,
            $email,
            $input->phone !== null ? PhoneNumber::from($input->phone) : null,
            $input->profileIds,
            $now,
        );

        $oldPhoto = null;
        if ($photoTempPath !== null) {
            $oldPhoto = $user->photoPath();
            $user->replacePhoto($this->photos->store($photoTempPath), $now);
        }

        $this->users->save($user);

        if ($oldPhoto !== null) {
            $this->photos->delete($oldPhoto);
        }

        $this->afterWrite();
        $this->audit->record('updated', 'user', $user->id(), $user->code()->value(), $actor, $before, $user->snapshot());

        return $user;
    }

    public function delete(string $id, Actor $actor): void
    {
        if ($actor->id === $id) {
            throw IdentityConflict::cannotDeleteSelf();
        }

        $user = $this->get($id);

        $this->users->delete($user);
        $this->refreshTokens->revokeAllForUser($user->id(), $this->clock->now());
        if ($user->photoPath() !== null) {
            $this->photos->delete($user->photoPath());
        }

        $this->afterWrite();
        $this->audit->record('deleted', 'user', $user->id(), $user->code()->value(), $actor, $user->snapshot(), null);
    }

    public function get(string $id): User
    {
        return $this->users->find($id) ?? throw NotFound::entity('Usuario', $id);
    }

    public function detail(string $id): UserDetail
    {
        $user = $this->get($id);

        return new UserDetail($user, $this->profiles->findMany($user->profileIds()));
    }

    /** @return Page<User> */
    public function list(ListQuery $query): Page
    {
        return $this->cache->remember(self::CACHE_NAMESPACE, $query->fingerprint(), self::CACHE_TTL, fn () => $this->users->paginate($query));
    }

    public function export(ListQuery $query, ExportFormat $format): ExportedFile
    {
        $profileNames = [];
        foreach ($this->profiles->all() as $profile) {
            $profileNames[$profile->id()] = $profile->name();
        }

        $rows = (function () use ($query, $profileNames): iterable {
            foreach ($this->users->cursor($query->withoutPagination()) as $user) {
                yield [
                    'code' => $user->code()->value(),
                    'email' => $user->email()->value(),
                    'name' => $user->name(),
                    'phone' => $user->phone()?->value() ?? '—',
                    'profiles' => implode(', ', array_filter(array_map(
                        static fn (string $id): ?string => $profileNames[$id] ?? null,
                        $user->profileIds(),
                    ))) ?: '—',
                    'created_at' => $user->createdAt(),
                ];
            }
        })();

        return $this->exporter->export(new ExportDocument(
            title: 'Listado de usuarios',
            filenameBase: 'usuarios',
            columns: [
                'code' => 'Código',
                'email' => 'Usuario',
                'name' => 'Nombre',
                'phone' => 'Teléfono',
                'profiles' => 'Perfiles',
                'created_at' => 'Fecha de creación',
            ],
            rows: $rows,
            subtitle: $query->search !== null ? "Filtro: \"{$query->search}\"" : null,
        ), $format);
    }

    /**
     * @param list<string> $profileIds
     */
    private function assertProfilesExist(array $profileIds): void
    {
        $unique = array_values(array_unique($profileIds));
        if (count($this->profiles->findMany($unique)) !== count($unique)) {
            throw InvalidValue::for('profile_ids', 'Uno o más perfiles seleccionados no existen.');
        }
    }

    private function afterWrite(): void
    {
        $this->cache->invalidate(self::CACHE_NAMESPACE, 'dashboard', 'profiles');
        $this->authUsers->invalidate();
    }
}
