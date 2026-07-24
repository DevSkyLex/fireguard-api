<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\Contract;

use Compliance\Application\Contract\EquipmentComplianceRow;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test EquipmentComplianceRowTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentComplianceRow::class)]
final class EquipmentComplianceRowTest extends TestCase
{
  #[Test]
  public function testConstructorExposesTheProvidedCounts(): void
  {
    $row = new EquipmentComplianceRow(totalCount: 12, activeCount: 9);

    self::assertSame(12, $row->totalCount);
    self::assertSame(9, $row->activeCount);
  }

  #[Test]
  public function testConstructorDefaultsToZeroCounts(): void
  {
    $row = new EquipmentComplianceRow();

    self::assertSame(0, $row->totalCount);
    self::assertSame(0, $row->activeCount);
  }
}
