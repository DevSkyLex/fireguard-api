<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\Exception;

use Authorization\Domain\Exception\RoleNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RoleNotFoundExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RoleNotFoundException::class)]
final class RoleNotFoundExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testWithIdCreatesMessage(): void
  {
    $exception = RoleNotFoundException::withId('role-123');

    $this->assertStringContainsString('role-123', $exception->getMessage());
  }

  #[Test]
  public function testWithNameCreatesMessage(): void
  {
    $exception = RoleNotFoundException::withName('admin');

    $this->assertStringContainsString('admin', $exception->getMessage());
  }
  // #endregion
}
