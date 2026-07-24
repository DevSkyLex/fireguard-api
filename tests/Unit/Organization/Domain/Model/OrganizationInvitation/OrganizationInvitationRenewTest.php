<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Model\OrganizationInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationInvitationStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;

#[CoversClass(OrganizationInvitation::class)]
final class OrganizationInvitationRenewTest extends TestCase
{
  #[Test]
  public function testRenewResetsExpiredInvitationToPendingWithNewToken(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::EXPIRED, new DateTimeImmutable('-1 day'));
    $newExpiresAt = new DateTimeImmutable('+7 days');

    $invitation->renew('new-hashed-token', $newExpiresAt);

    self::assertSame(OrganizationInvitationStatus::PENDING, $invitation->status());
    self::assertSame('new-hashed-token', $invitation->tokenHash());
    self::assertEquals($newExpiresAt, $invitation->expiresAt());
  }

  #[Test]
  public function testRenewRejectsAcceptedInvitation(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::ACCEPTED, new DateTimeImmutable('+7 days'));

    $this->expectException(InvalidArgumentException::class);

    $invitation->renew('new-hashed-token', new DateTimeImmutable('+7 days'));
  }

  /**
   * Reconstitutes an invitation in a given status for renewal assertions.
   */
  private function reconstitute(OrganizationInvitationStatus $status, DateTimeImmutable $expiresAt): OrganizationInvitation
  {
    return OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId('550e8400-e29b-41d4-a716-446655445500'),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655445501'),
      email: new Email('member@example.com'),
      tokenHash: 'old-hashed-token',
      invitedByUserId: '550e8400-e29b-41d4-a716-446655445502',
      status: $status,
      expiresAt: $expiresAt,
      createdAt: new DateTimeImmutable('-10 days'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );
  }
}
