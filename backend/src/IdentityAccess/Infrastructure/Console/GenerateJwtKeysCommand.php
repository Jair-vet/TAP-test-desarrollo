<?php

declare(strict_types=1);

namespace Tap\IdentityAccess\Infrastructure\Console;

use Illuminate\Console\Command;
use Tap\IdentityAccess\Infrastructure\Security\JwtKeyStore;

/**
 * php artisan security:jwt-keys — genera el par de llaves Ed25519 que firma los JWT.
 * Lo ejecuta docker/php/entrypoint.sh al arrancar; si las llaves ya existen, no las toca.
 */
final class GenerateJwtKeysCommand extends Command
{
    protected $signature = 'security:jwt-keys {--force : Reemplaza las llaves existentes (cierra todas las sesiones)}';

    protected $description = 'Genera el par de llaves Ed25519 para firmar los JWT';

    public function handle(JwtKeyStore $keys): int
    {
        $paths = $keys->generate((bool) $this->option('force'));
        $this->components->info('Llaves JWT listas: ' . $paths['public']);

        return self::SUCCESS;
    }
}
