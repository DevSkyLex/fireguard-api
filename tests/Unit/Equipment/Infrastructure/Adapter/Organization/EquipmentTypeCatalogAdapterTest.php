<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Adapter\Organization;

use Equipment\Domain\ValueObject\EquipmentType;
use Equipment\Infrastructure\Adapter\Organization\EquipmentTypeCatalogAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_column;
use function count;

/**
 * Test EquipmentTypeCatalogAdapterTest.
 *
 * Organization reads the equipment taxonomy through this port to build its
 * settings screens, so the adapter must expose the domain enum as-is rather
 * than a hand-maintained copy that can silently fall behind.
 *
 * @category Adapter Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentTypeCatalogAdapter::class)]
final class EquipmentTypeCatalogAdapterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testValuesMirrorTheDomainEnum(): void
  {
    self::assertSame(EquipmentType::values(), new EquipmentTypeCatalogAdapter()->values());
  }

  #[Test]
  public function testDescriptorsCoverEveryCaseWithItsLabel(): void
  {
    $descriptors = new EquipmentTypeCatalogAdapter()->descriptors();

    self::assertCount(count(EquipmentType::cases()), $descriptors);
    self::assertSame(EquipmentType::values(), array_column($descriptors, 'value'));

    $labels = array_column($descriptors, 'label', 'value');
    foreach (EquipmentType::cases() as $case) {
      self::assertSame($case->label(), $labels[$case->value]);
      self::assertNotSame('', $labels[$case->value]);
    }
  }
  // #endregion
}
