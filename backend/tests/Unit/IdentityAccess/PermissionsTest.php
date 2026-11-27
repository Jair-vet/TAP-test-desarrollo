<?php

declare(strict_types=1);

namespace Tests\Unit\IdentityAccess;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tap\IdentityAccess\Domain\Profile\Action;
use Tap\IdentityAccess\Domain\Profile\PermissionSet;
use Tap\IdentityAccess\Domain\Profile\Profile;
use Tap\IdentityAccess\Domain\Profile\Section;
use Tap\IdentityAccess\Domain\Profile\SectionAccess;
use Tap\Shared\Domain\Exception\InvalidValue;
use Tap\Shared\Domain\ValueObject\EntityCode;

final class PermissionsTest extends TestCase
{
    public function test_cualquier_accion_implica_consultar(): void
    {
        $access = SectionAccess::from('products', ['delete']);

        self::assertSame(['view', 'delete'], $access->actionValues());
    }

    public function test_rechaza_seccion_o_accion_inexistente(): void
    {
        $this->expectException(InvalidValue::class);
        SectionAccess::from('audit', ['delete']); // la bitácora es de solo lectura
    }

    public function test_permisos_efectivos_son_la_union_de_perfiles(): void
    {
        $now = new DateTimeImmutable();
        $warehouse = Profile::create('a', EntityCode::generate('PRF', 1), 'Almacén', null, [SectionAccess::from('products', ['create'])], $now);
        $readonly = Profile::create('b', EntityCode::generate('PRF', 2), 'Consulta', null, [
            SectionAccess::from('products', ['export']),
            SectionAccess::from('users', ['view']),
        ], $now);

        $permissions = PermissionSet::fromProfiles([$warehouse, $readonly]);

        self::assertTrue($permissions->can(Section::Products, Action::Create));
        self::assertTrue($permissions->can(Section::Products, Action::Export));
        self::assertTrue($permissions->can(Section::Users, Action::View));
        self::assertFalse($permissions->can(Section::Users, Action::Delete));
        self::assertFalse($permissions->can(Section::Profiles, Action::View));
    }

    public function test_perfil_sin_secciones_no_es_valido(): void
    {
        $this->expectException(InvalidValue::class);
        Profile::create('a', EntityCode::generate('PRF', 1), 'Vacío', null, [], new DateTimeImmutable());
    }
}
