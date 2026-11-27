<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Tap\Catalog\Domain\Price;
use Tap\Catalog\Domain\Product;
use Tap\Catalog\Domain\ProductRepository;
use Tap\IdentityAccess\Application\Port\PasswordHasher;
use Tap\IdentityAccess\Application\Port\PhotoStorage;
use Tap\IdentityAccess\Domain\Profile\Profile;
use Tap\IdentityAccess\Domain\Profile\ProfileRepository;
use Tap\IdentityAccess\Domain\Profile\SectionAccess;
use Tap\IdentityAccess\Domain\User\PhoneNumber;
use Tap\IdentityAccess\Domain\User\User;
use Tap\IdentityAccess\Domain\User\UserRepository;
use Tap\Shared\Application\Port\Clock;
use Tap\Shared\Application\Port\QueryCache;
use Tap\Shared\Application\Port\SequenceGenerator;
use Tap\Shared\Domain\ValueObject\Email;
use Tap\Shared\Domain\ValueObject\EntityCode;

/**
 * Datos de demostración (idempotente: si el admin ya existe no hace nada).
 *
 *   admin@tap.test     → Administrador (todo)
 *   almacen@tap.test   → Almacén (productos completo)
 *   consulta@tap.test  → Consulta (solo ver productos y usuarios)
 *
 * Contraseñas: SEED_ADMIN_PASSWORD y SEED_DEMO_PASSWORD del .env
 * (scripts/setup.sh las genera aleatorias).
 */
final class DatabaseSeeder extends Seeder
{
    public function run(
        ProfileRepository $profiles,
        UserRepository $users,
        ProductRepository $products,
        SequenceGenerator $sequences,
        PasswordHasher $hasher,
        PhotoStorage $photos,
        Clock $clock,
        QueryCache $cache,
    ): void {
        if ($users->findByEmail(Email::from('admin@tap.test')) !== null) {
            $this->command->info('Datos demo ya existen: se omite el seeder.');

            return;
        }

        $now = $clock->now();
        $all = ['view', 'create', 'update', 'delete', 'export'];

        $profileDefs = [
            'admin' => ['Administrador', 'Acceso total al sistema.', [
                ['products', $all], ['users', $all], ['profiles', $all], ['audit', ['view', 'export']],
            ]],
            'warehouse' => ['Almacén', 'Gestiona el catálogo de productos.', [
                ['products', $all],
            ]],
            'readonly' => ['Consulta', 'Solo lectura de productos y usuarios.', [
                ['products', ['view']], ['users', ['view']],
            ]],
            'hr' => ['Recursos Humanos', 'Administra usuarios; consulta perfiles.', [
                ['users', $all], ['profiles', ['view']],
            ]],
        ];

        $profileIds = [];
        foreach ($profileDefs as $key => [$name, $description, $sections]) {
            $profile = Profile::create(
                $profiles->nextId(),
                EntityCode::generate(Profile::CODE_PREFIX, $sequences->next('profiles')),
                $name,
                $description,
                array_map(static fn (array $s): SectionAccess => SectionAccess::from($s[0], $s[1]), $sections),
                $now,
            );
            $profiles->save($profile);
            $profileIds[$key] = $profile->id();
        }

        $adminPassword = (string) (config('security.seed.admin_password') ?: 'Admin#2026tap');
        $demoPassword = (string) (config('security.seed.demo_password') ?: 'Demo#2026tap');

        $userDefs = [
            ['Administrador TAP', 'admin@tap.test', '+523141234567', ['admin'], $adminPassword, '#0B0B0C'],
            ['Laura Almacén', 'almacen@tap.test', '+523141112233', ['warehouse'], $demoPassword, '#B45309'],
            ['Carlos Consulta', 'consulta@tap.test', null, ['readonly'], $demoPassword, '#1D4ED8'],
            ['Ana Recursos Humanos', 'rh@tap.test', '+523125550101', ['hr'], $demoPassword, '#047857'],
        ];

        foreach ($userDefs as [$name, $email, $phone, $profileKeys, $password, $color]) {
            $user = User::register(
                $users->nextId(),
                EntityCode::generate(User::CODE_PREFIX, $sequences->next('users')),
                $name,
                Email::from($email),
                $phone === null ? null : PhoneNumber::from($phone),
                $photos->store($this->avatar($name, $color)),
                array_map(static fn (string $k): string => $profileIds[$k], $profileKeys),
                $hasher->hash($password),
                $now,
            );
            // Usuarios demo listos para entrar (sin forzar cambio de contraseña)
            $user->changePassword($hasher->hash($password), $now);
            $users->save($user);
        }

        $catalog = [
            ['Casco de seguridad clase E', '3M', '289.90'], ['Chaleco reflejante naranja', 'Truper', '149.50'],
            ['Guantes de nitrilo (par)', 'Ansell', '45.00'], ['Botas dieléctricas', 'Caterpillar', '899.00'],
            ['Lentes de seguridad claros', 'Steelpro', '79.90'], ['Tapones auditivos (caja 50)', '3M', '215.00'],
            ['Arnés de cuerpo completo', 'MSA', '999.99'], ['Línea de vida 1.8 m', 'MSA', '749.00'],
            ['Candado de bloqueo LOTO', 'Master Lock', '329.00'], ['Etiqueta de bloqueo (10 pzas)', 'Brady', '119.00'],
            ['Precinto de seguridad contenedor', 'TydenBrooks', '18.50'], ['Sello de alta seguridad ISO 17712', 'TydenBrooks', '35.00'],
            ['Cinta delimitadora amarilla', 'Truper', '89.00'], ['Cono vial 70 cm', 'Pretul', '199.00'],
            ['Lámpara de mano LED', 'Energizer', '259.00'], ['Radio portátil UHF', 'Motorola', '989.00'],
            ['Batería para radio portátil', 'Motorola', '549.00'], ['Extintor PQS 4.5 kg', 'Badger', '899.90'],
            ['Botiquín de primeros auxilios', 'Cruz Verde', '459.00'], ['Respirador media cara', '3M', '689.00'],
            ['Cartucho para vapores orgánicos', '3M', '279.00'], ['Overol de algodón', 'Dickies', '799.00'],
            ['Impermeable con capucha', 'Truper', '239.00'], ['Silbato de señalización', 'Fox 40', '65.00'],
            ['Bandera de señalización', 'Pretul', '55.50'], ['Estrobo de nylon 2 t', 'Lift-All', '689.00'],
            ['Grillete de acero 3/4"', 'Crosby', '245.00'], ['Gancho giratorio 2 t', 'Crosby', '899.00'],
            ['Cuña para llanta', 'Checkers', '389.00'], ['Espejo convexo 60 cm', 'Seton', '949.00'],
            ['Termómetro infrarrojo', 'Fluke', '999.00'], ['Multímetro digital', 'Fluke', '985.00'],
            ['Cinta métrica 8 m', 'Stanley', '199.90'], ['Marcador industrial amarillo', 'Markal', '42.00'],
            ['Tablilla portapapeles', 'Pretul', '59.00'], ['Gafete con porta credencial', 'Acco', '25.00'],
        ];

        foreach ($catalog as $i => [$name, $brand, $price]) {
            $products->save(Product::create(
                $products->nextId(),
                EntityCode::generate(Product::CODE_PREFIX, $sequences->next('products')),
                $name,
                $brand,
                Price::from($price),
                // Fechas escalonadas para que el orden por fecha se note en la tabla
                $now->modify('-' . (count($catalog) - $i) * 7 . ' hours'),
            ));
        }

        $cache->invalidate('products', 'users', 'profiles', 'dashboard', 'auth');

        $this->command->info('Datos demo creados: 4 perfiles, 4 usuarios y ' . count($catalog) . ' productos.');
    }

    /** Avatar PNG con iniciales (la foto es obligatoria en el examen). */
    private function avatar(string $name, string $hex): string
    {
        $size = 256;
        $img = imagecreatetruecolor($size, $size);
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x') ?: [0, 0, 0];
        imagefill($img, 0, 0, (int) imagecolorallocate($img, (int) $r, (int) $g, (int) $b));
        $yellow = (int) imagecolorallocate($img, 255, 212, 0);

        $words = preg_split('/\s+/', $name) ?: [];
        $initials = mb_strtoupper(mb_substr($words[0] ?? 'U', 0, 1) . mb_substr($words[1] ?? '', 0, 1));
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

        $box = imagettfbbox(96, 0, $font, $initials) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $x = (int) (($size - ($box[2] - $box[0])) / 2);
        $y = (int) (($size - ($box[7] - $box[1])) / 2);
        imagettftext($img, 96, 0, $x, $y, $yellow, $font, $initials);

        $path = tempnam(sys_get_temp_dir(), 'avatar_') . '.png';
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }
}
