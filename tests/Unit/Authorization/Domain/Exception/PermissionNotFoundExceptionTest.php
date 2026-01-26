<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\Exception;

use Authorization\Domain\Exception\PermissionNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test PermissionNotFoundExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PermissionNotFoundException::class)]
final class PermissionNotFoundExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testWithIdCreatesMessage(): void
  {
    $exception = PermissionNotFoundException::withId('perm-123');

    $this->assertStringContainsString('perm-123', $exception->getMessage());
  }

  #[Test]
  public function testWithNameCreatesMessage(): void
  {
    $exception = PermissionNotFoundException::withName('users.read');

    $this->assertStringContainsString('users.read', $exception->getMessage());
  }
  // #endregion
}
