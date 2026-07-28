<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\Common\Collections\ArrayCollection;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationInvitationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationInvitationRecordTest.
 *
 * @category Record Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationInvitationRecord::class)]
final class OrganizationInvitationRecordTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testConstructorInitializesTheRoleAssignmentsCollection(): void
  {
    $record = new OrganizationInvitationRecord();

    self::assertInstanceOf(ArrayCollection::class, $record->roleAssignments);
    self::assertCount(0, $record->roleAssignments);
  }

  #[Test]
  public function testANewRecordStartsPendingAndUnresolved(): void
  {
    $record = new OrganizationInvitationRecord();

    self::assertSame('pending', $record->status);
    self::assertNull($record->organization);
    self::assertNull($record->acceptedByUserId);
    self::assertNull($record->revokedByUserId);
    self::assertNull($record->acceptedAt);
    self::assertNull($record->revokedAt);
  }
  // #endregion
}
