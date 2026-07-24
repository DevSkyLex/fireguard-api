<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\Contract\Intervention;

use Equipment\Application\Contract\Intervention\ServicedEquipmentEntry;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ServicedEquipmentEntryTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ServicedEquipmentEntry::class)]
final class ServicedEquipmentEntryTest extends TestCase
{
  #[Test]
  public function itExposesReadonlyProperties(): void
  {
    $entry = new ServicedEquipmentEntry('equip-1', 'inspected', 'token-9', 'wi-2');

    self::assertSame('equip-1', $entry->equipmentId);
    self::assertSame('inspected', $entry->action);
    self::assertSame('token-9', $entry->changeToken);
    self::assertSame('wi-2', $entry->workItemId);
  }

  #[Test]
  public function itAllowsNullWorkItem(): void
  {
    $entry = new ServicedEquipmentEntry('equip-2', 'serviced', 'token-10', null);

    self::assertNull($entry->workItemId);
  }
}
