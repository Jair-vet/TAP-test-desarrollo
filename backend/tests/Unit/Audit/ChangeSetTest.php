<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use PHPUnit\Framework\TestCase;
use Tap\Audit\Domain\ChangeSet;

final class ChangeSetTest extends TestCase
{
    public function test_detecta_solo_los_campos_que_cambiaron(): void
    {
        $changes = ChangeSet::between(
            ['name' => 'Casco', 'brand' => '3M', 'price' => '120.00'],
            ['name' => 'Casco', 'brand' => '3M', 'price' => '150.00'],
        );

        self::assertSame([['field' => 'price', 'before' => '120.00', 'after' => '150.00']], $changes);
    }

    public function test_alta_y_baja(): void
    {
        self::assertCount(2, ChangeSet::between(null, ['a' => 1, 'b' => 2]));
        self::assertCount(2, ChangeSet::between(['a' => 1, 'b' => 2], null));
    }

    public function test_aplana_subdocumentos_y_listas(): void
    {
        $changes = ChangeSet::between(
            ['sections' => ['products' => 'view'], 'profile_ids' => ['a']],
            ['sections' => ['products' => 'view, update'], 'profile_ids' => ['a', 'b']],
        );

        self::assertSame(['profile_ids', 'sections.products'], array_column($changes, 'field'));
    }
}
