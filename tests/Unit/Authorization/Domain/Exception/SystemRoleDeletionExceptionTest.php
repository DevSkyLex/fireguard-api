<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\Exception;

use Authorization\Domain\Exception\SystemRoleDeletionException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test SystemRoleDeletionExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SystemRoleDeletionException::class)]
final class SystemRoleDeletionExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testForRoleCreatesMessage(): void
  {
    $exception = SystemRoleDeletionException::forRole('role-999');

    $this->assertStringContainsString('role-999', $exception->getMessage());
  }
  // #endregion
}
