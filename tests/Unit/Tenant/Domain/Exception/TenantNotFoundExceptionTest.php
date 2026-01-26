<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Domain\Exception;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Tenant\Domain\Exception\TenantNotFoundException;

/**
 * Test TenantNotFoundExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TenantNotFoundException::class)]
final class TenantNotFoundExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testWithIdCreatesMessage(): void
  {
    $exception = TenantNotFoundException::withId('tenant-1');

    $this->assertStringContainsString('tenant-1', $exception->getMessage());
  }
  // #endregion
}
