<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Exception;

use Organization\Domain\Exception\OrganizationOwnershipUnchangedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test OrganizationOwnershipUnchangedExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationOwnershipUnchangedException::class)]
final class OrganizationOwnershipUnchangedExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testWithOwnerUserIdCreatesMessage(): void
  {
    $exception = OrganizationOwnershipUnchangedException::withOwnerUserId('owner-1');

    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('User "owner-1" already owns this organization.', $exception->getMessage());
  }
  // #endregion
}
