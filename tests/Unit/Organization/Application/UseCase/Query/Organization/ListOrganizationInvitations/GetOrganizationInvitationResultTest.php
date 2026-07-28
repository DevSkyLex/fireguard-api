<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations;

use DateTimeImmutable;
use Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations\GetOrganizationInvitationResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetOrganizationInvitationResultTest.
 *
 * The acceptance and revocation fields are mutually exclusive in practice, so
 * the result has to keep them independently nullable rather than collapsing
 * them into a single "resolved by" pair.
 *
 * @category UseCase Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetOrganizationInvitationResult::class)]
final class GetOrganizationInvitationResultTest extends TestCase
{
  // #region Constants
  private const string INVITATION_ID = '550e8400-e29b-41d4-a716-446655480001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655480002';

  private const string INVITER_ID = '550e8400-e29b-41d4-a716-446655480003';

  private const string ACCEPTOR_ID = '550e8400-e29b-41d4-a716-446655480004';
  // #endregion

  // #region Methods
  #[Test]
  public function testConstructorCarriesTheFullyResolvedInvitation(): void
  {
    $expiresAt = new DateTimeImmutable('2026-02-01T00:00:00+00:00');
    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-05T00:00:00+00:00');
    $acceptedAt = new DateTimeImmutable('2026-01-05T09:00:00+00:00');

    $invitation = new GetOrganizationInvitationResult(
      id: self::INVITATION_ID,
      organizationId: self::ORGANIZATION_ID,
      email: 'new.member@fireguard.local',
      status: 'accepted',
      invitedByUserId: self::INVITER_ID,
      acceptedByUserId: self::ACCEPTOR_ID,
      revokedByUserId: null,
      expiresAt: $expiresAt,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      acceptedAt: $acceptedAt,
      revokedAt: null,
      roleIds: ['role-a', 'role-b'],
    );

    self::assertSame(self::INVITATION_ID, $invitation->id);
    self::assertSame(self::ORGANIZATION_ID, $invitation->organizationId);
    self::assertSame('new.member@fireguard.local', $invitation->email);
    self::assertSame('accepted', $invitation->status);
    self::assertSame(self::INVITER_ID, $invitation->invitedByUserId);
    self::assertSame(self::ACCEPTOR_ID, $invitation->acceptedByUserId);
    self::assertNull($invitation->revokedByUserId);
    self::assertSame($expiresAt, $invitation->expiresAt);
    self::assertSame($createdAt, $invitation->createdAt);
    self::assertSame($updatedAt, $invitation->updatedAt);
    self::assertSame($acceptedAt, $invitation->acceptedAt);
    self::assertNull($invitation->revokedAt);
    self::assertSame(['role-a', 'role-b'], $invitation->roleIds);
  }

  #[Test]
  public function testConstructorLeavesEveryResolutionFieldNullOnAPendingInvitation(): void
  {
    $invitation = new GetOrganizationInvitationResult(
      id: self::INVITATION_ID,
      organizationId: self::ORGANIZATION_ID,
      email: 'pending@fireguard.local',
      status: 'pending',
      invitedByUserId: self::INVITER_ID,
      acceptedByUserId: null,
      revokedByUserId: null,
      expiresAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      acceptedAt: null,
      revokedAt: null,
      roleIds: [],
    );

    self::assertSame('pending', $invitation->status);
    self::assertNull($invitation->acceptedByUserId);
    self::assertNull($invitation->acceptedAt);
    self::assertNull($invitation->revokedByUserId);
    self::assertNull($invitation->revokedAt);
    self::assertSame([], $invitation->roleIds);
  }
  // #endregion
}
