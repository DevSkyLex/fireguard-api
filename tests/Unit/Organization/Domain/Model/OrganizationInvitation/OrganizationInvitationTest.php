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
final class OrganizationInvitationTest extends TestCase
{
  #[Test]
  public function testCreateInitializesPendingInvitationWithMatchingTimestamps(): void
  {
    $id = new OrganizationInvitationId('550e8400-e29b-41d4-a716-446655440000');
    $organizationId = new OrganizationId('550e8400-e29b-41d4-a716-446655440001');
    $email = new Email('invitee@example.com');
    $expiresAt = new DateTimeImmutable('+7 days');

    $invitation = OrganizationInvitation::create(
      id: $id,
      organizationId: $organizationId,
      email: $email,
      tokenHash: 'hashed-token',
      invitedByUserId: '550e8400-e29b-41d4-a716-446655440002',
      expiresAt: $expiresAt,
    );

    self::assertSame($id, $invitation->id());
    self::assertSame($organizationId, $invitation->organizationId());
    self::assertSame($email, $invitation->email());
    self::assertSame('hashed-token', $invitation->tokenHash());
    self::assertSame('550e8400-e29b-41d4-a716-446655440002', $invitation->invitedByUserId());
    self::assertSame(OrganizationInvitationStatus::PENDING, $invitation->status());
    self::assertSame($expiresAt, $invitation->expiresAt());
    self::assertSame($invitation->createdAt(), $invitation->updatedAt());
    self::assertNull($invitation->acceptedAt());
    self::assertNull($invitation->acceptedByUserId());
    self::assertNull($invitation->revokedAt());
    self::assertNull($invitation->revokedByUserId());
  }

  #[Test]
  public function testReconstituteRestoresEveryPersistedField(): void
  {
    $acceptedAt = new DateTimeImmutable('-2 days');
    $revokedAt = new DateTimeImmutable('-1 day');

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId('550e8400-e29b-41d4-a716-446655440010'),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655440011'),
      email: new Email('restored@example.com'),
      tokenHash: 'restored-token',
      invitedByUserId: '550e8400-e29b-41d4-a716-446655440012',
      status: OrganizationInvitationStatus::ACCEPTED,
      expiresAt: new DateTimeImmutable('+3 days'),
      createdAt: new DateTimeImmutable('-10 days'),
      updatedAt: new DateTimeImmutable('-2 days'),
      acceptedAt: $acceptedAt,
      acceptedByUserId: '550e8400-e29b-41d4-a716-446655440013',
      revokedAt: $revokedAt,
      revokedByUserId: '550e8400-e29b-41d4-a716-446655440014',
    );

    self::assertSame(OrganizationInvitationStatus::ACCEPTED, $invitation->status());
    self::assertSame('restored-token', $invitation->tokenHash());
    self::assertSame($acceptedAt, $invitation->acceptedAt());
    self::assertSame('550e8400-e29b-41d4-a716-446655440013', $invitation->acceptedByUserId());
    self::assertSame($revokedAt, $invitation->revokedAt());
    self::assertSame('550e8400-e29b-41d4-a716-446655440014', $invitation->revokedByUserId());
  }

  #[Test]
  public function testIsExpiredReturnsTrueWhenExpiryPrecedesReference(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::PENDING, new DateTimeImmutable('-1 day'));

    self::assertTrue($invitation->isExpired(new DateTimeImmutable('now')));
  }

  #[Test]
  public function testIsExpiredReturnsFalseWhenExpiryFollowsReference(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::PENDING, new DateTimeImmutable('+1 day'));

    self::assertFalse($invitation->isExpired(new DateTimeImmutable('now')));
  }

  #[Test]
  public function testIsExpiredDefaultsToNowWhenReferenceOmitted(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::PENDING, new DateTimeImmutable('-1 hour'));

    self::assertTrue($invitation->isExpired());
  }

  #[Test]
  public function testAcceptMarksInvitationAcceptedWithProvidedDate(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::PENDING, new DateTimeImmutable('+7 days'));
    $acceptedAt = new DateTimeImmutable('now');

    $invitation->accept('550e8400-e29b-41d4-a716-446655440020', $acceptedAt);

    self::assertSame(OrganizationInvitationStatus::ACCEPTED, $invitation->status());
    self::assertSame($acceptedAt, $invitation->acceptedAt());
    self::assertSame('550e8400-e29b-41d4-a716-446655440020', $invitation->acceptedByUserId());
    self::assertSame($acceptedAt, $invitation->updatedAt());
  }

  #[Test]
  public function testAcceptDefaultsToNowWhenDateOmitted(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::PENDING, new DateTimeImmutable('+7 days'));

    $invitation->accept('550e8400-e29b-41d4-a716-446655440021');

    self::assertSame(OrganizationInvitationStatus::ACCEPTED, $invitation->status());
    self::assertInstanceOf(DateTimeImmutable::class, $invitation->acceptedAt());
  }

  #[Test]
  public function testAcceptRejectsNonPendingInvitation(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::REVOKED, new DateTimeImmutable('+7 days'));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Only pending invitations can be accepted.');

    $invitation->accept('550e8400-e29b-41d4-a716-446655440022');
  }

  #[Test]
  public function testAcceptExpiresInvitationAndRejectsWhenPastExpiry(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::PENDING, new DateTimeImmutable('-1 day'));

    try {
      $invitation->accept('550e8400-e29b-41d4-a716-446655440023', new DateTimeImmutable('now'));
      self::fail('Expected an InvalidArgumentException for an expired invitation.');
    } catch (InvalidArgumentException $exception) {
      self::assertSame('Invitation has expired.', $exception->getMessage());
    }

    self::assertSame(OrganizationInvitationStatus::EXPIRED, $invitation->status());
    self::assertNull($invitation->acceptedAt());
  }

  #[Test]
  public function testRevokeMarksInvitationRevokedWithProvidedDate(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::PENDING, new DateTimeImmutable('+7 days'));
    $revokedAt = new DateTimeImmutable('now');

    $invitation->revoke('550e8400-e29b-41d4-a716-446655440030', $revokedAt);

    self::assertSame(OrganizationInvitationStatus::REVOKED, $invitation->status());
    self::assertSame($revokedAt, $invitation->revokedAt());
    self::assertSame('550e8400-e29b-41d4-a716-446655440030', $invitation->revokedByUserId());
    self::assertSame($revokedAt, $invitation->updatedAt());
  }

  #[Test]
  public function testRevokeDefaultsToNowWhenDateOmitted(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::PENDING, new DateTimeImmutable('+7 days'));

    $invitation->revoke('550e8400-e29b-41d4-a716-446655440031');

    self::assertSame(OrganizationInvitationStatus::REVOKED, $invitation->status());
    self::assertInstanceOf(DateTimeImmutable::class, $invitation->revokedAt());
  }

  #[Test]
  public function testRevokeRejectsNonPendingInvitation(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::ACCEPTED, new DateTimeImmutable('+7 days'));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Only pending invitations can be revoked.');

    $invitation->revoke('550e8400-e29b-41d4-a716-446655440032');
  }

  #[Test]
  public function testRenewReissuesPendingInvitationWithFreshToken(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::PENDING, new DateTimeImmutable('+1 day'));
    $newExpiresAt = new DateTimeImmutable('+14 days');
    $renewedAt = new DateTimeImmutable('now');

    $invitation->renew('fresh-token', $newExpiresAt, $renewedAt);

    self::assertSame(OrganizationInvitationStatus::PENDING, $invitation->status());
    self::assertSame('fresh-token', $invitation->tokenHash());
    self::assertSame($newExpiresAt, $invitation->expiresAt());
    self::assertSame($renewedAt, $invitation->updatedAt());
  }

  #[Test]
  public function testRenewDefaultsToNowWhenDateOmitted(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::PENDING, new DateTimeImmutable('+1 day'));

    $invitation->renew('fresh-token', new DateTimeImmutable('+14 days'));

    self::assertSame('fresh-token', $invitation->tokenHash());
    self::assertInstanceOf(DateTimeImmutable::class, $invitation->updatedAt());
  }

  #[Test]
  public function testRenewRejectsRevokedInvitation(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::REVOKED, new DateTimeImmutable('+7 days'));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Only pending or expired invitations can be resent.');

    $invitation->renew('fresh-token', new DateTimeImmutable('+14 days'));
  }

  #[Test]
  public function testExpireMarksPendingInvitationExpiredWhenPastExpiry(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::PENDING, new DateTimeImmutable('-1 day'));
    $expiredAt = new DateTimeImmutable('now');

    $invitation->expire($expiredAt);

    self::assertSame(OrganizationInvitationStatus::EXPIRED, $invitation->status());
    self::assertSame($expiredAt, $invitation->updatedAt());
  }

  #[Test]
  public function testExpireDefaultsToNowWhenDateOmitted(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::PENDING, new DateTimeImmutable('-1 hour'));

    $invitation->expire();

    self::assertSame(OrganizationInvitationStatus::EXPIRED, $invitation->status());
  }

  #[Test]
  public function testExpireDoesNothingForNonPendingInvitation(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::ACCEPTED, new DateTimeImmutable('-1 day'));

    $invitation->expire(new DateTimeImmutable('now'));

    self::assertSame(OrganizationInvitationStatus::ACCEPTED, $invitation->status());
  }

  #[Test]
  public function testExpireLeavesPendingInvitationUntouchedWhenNotYetExpired(): void
  {
    $invitation = $this->reconstitute(OrganizationInvitationStatus::PENDING, new DateTimeImmutable('+7 days'));

    $invitation->expire(new DateTimeImmutable('now'));

    self::assertSame(OrganizationInvitationStatus::PENDING, $invitation->status());
  }

  /**
   * Reconstitutes a pending-friendly invitation in a given status and expiry.
   */
  private function reconstitute(OrganizationInvitationStatus $status, DateTimeImmutable $expiresAt): OrganizationInvitation
  {
    return OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId('550e8400-e29b-41d4-a716-446655440100'),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655440101'),
      email: new Email('member@example.com'),
      tokenHash: 'old-hashed-token',
      invitedByUserId: '550e8400-e29b-41d4-a716-446655440102',
      status: $status,
      expiresAt: $expiresAt,
      createdAt: new DateTimeImmutable('-10 days'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );
  }
}
