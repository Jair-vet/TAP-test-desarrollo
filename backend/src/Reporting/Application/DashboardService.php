<?php

declare(strict_types=1);

namespace Tap\Reporting\Application;

use Tap\Audit\Application\AuditService;
use Tap\Audit\Domain\AuditEntry;
use Tap\Catalog\Domain\ProductRepository;
use Tap\IdentityAccess\Domain\Profile\ProfileRepository;
use Tap\IdentityAccess\Domain\User\UserRepository;
use Tap\Shared\Application\AuthenticatedUser;
use Tap\Shared\Application\Port\QueryCache;

/**
 * Read model del tablero de inicio. Solo muestra los contadores de las
 * secciones a las que el usuario tiene acceso. Cacheado 2 min.
 */
final class DashboardService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly UserRepository $users,
        private readonly ProfileRepository $profiles,
        private readonly AuditService $audit,
        private readonly QueryCache $cache,
    ) {
    }

    /**
     * @return array{counts: array<string, int>, recent: list<AuditEntry>}
     */
    public function summary(AuthenticatedUser $user): array
    {
        $counts = $this->cache->remember('dashboard', ['counts'], 120, fn (): array => [
            'products' => $this->products->count(),
            'users' => $this->users->count(),
            'profiles' => $this->profiles->count(),
        ]);

        $visible = array_filter(
            $counts,
            static fn (string $section): bool => $user->can($section, 'view'),
            ARRAY_FILTER_USE_KEY,
        );

        // Actividad reciente: solo de entidades que el usuario puede ver
        $entityTypes = array_keys(array_filter(
            ['product' => 'products', 'user' => 'users', 'profile' => 'profiles'],
            static fn (string $section): bool => $user->can($section, 'view'),
        ));

        $recent = $user->can('audit', 'view')
            ? $this->audit->latest(6)
            : ($entityTypes === [] ? [] : $this->audit->latest(6, $entityTypes));

        return ['counts' => $visible, 'recent' => $recent];
    }
}
