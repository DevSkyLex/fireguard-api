<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\ValueObject;

use Maintenance\Domain\ValueObject\MaintenanceOperationKind;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class MaintenanceOperationKindTest
 *
 * Verifies that a defect does not erase a performed control.
 *
 * @category Tests
 */
#[CoversClass(MaintenanceOperationKind::class)]
final class MaintenanceOperationKindTest extends TestCase
{
  // #region Methods
  /**
   * Method testCompletionDependsOnOperationKind
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCompletionDependsOnOperationKind(): void
  {
    self::assertSame(['control', 'maintenance'], MaintenanceOperationKind::values());
    self::assertTrue(MaintenanceOperationKind::CONTROL->completesOccurrence(false));
    self::assertTrue(MaintenanceOperationKind::CONTROL->completesOccurrence(true));
    self::assertFalse(MaintenanceOperationKind::MAINTENANCE->completesOccurrence(false));
    self::assertTrue(MaintenanceOperationKind::MAINTENANCE->completesOccurrence(true));
  }
  // #endregion
}
