<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationInvitationStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationInvitationStatus.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationInvitationStatus::class)]
final class OrganizationInvitationStatusTest extends TestCase
{
  #[Test]
  public function testIsPendingOnlyForPendingCase(): void
  {
    self::assertTrue(OrganizationInvitationStatus::PENDING->isPending());
    self::assertFalse(OrganizationInvitationStatus::ACCEPTED->isPending());
    self::assertFalse(OrganizationInvitationStatus::REVOKED->isPending());
    self::assertFalse(OrganizationInvitationStatus::EXPIRED->isPending());
  }
}
