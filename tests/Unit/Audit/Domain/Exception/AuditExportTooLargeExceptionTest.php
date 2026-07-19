<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Domain\Exception;

use Audit\Domain\Exception\AuditExportTooLargeException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AuditExportTooLargeExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuditExportTooLargeException::class)]
final class AuditExportTooLargeExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testExceedsCapBuildsAnActionableMessage(): void
  {
    $exception = AuditExportTooLargeException::exceedsCap(matched: 75_000, maxRows: 50_000);

    self::assertStringContainsString('75000', $exception->getMessage());
    self::assertStringContainsString('50000', $exception->getMessage());
    self::assertStringContainsString('from', $exception->getMessage());
  }
  // #endregion
}
