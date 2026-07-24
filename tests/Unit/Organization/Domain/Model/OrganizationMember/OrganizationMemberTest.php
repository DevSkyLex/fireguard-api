<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Model\OrganizationMember;

use DateTimeImmutable;
use DateTimeZone;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationMember.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationMember::class)]
final class OrganizationMemberTest extends TestCase
{
  private const string MEMBER_ID = '11111111-1111-4111-8111-111111111111';

  private const string ORGANIZATION_ID = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function testJoinCreatesActiveMember(): void
  {
    $member = OrganizationMember::join(
      OrganizationMemberId::fromString(self::MEMBER_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      'user-1',
    );

    self::assertSame(self::MEMBER_ID, $member->id()->value);
    self::assertSame(self::ORGANIZATION_ID, $member->organizationId()->value);
    self::assertSame('user-1', $member->userId());
    self::assertTrue($member->isActive());
  }

  #[Test]
  public function testReconstitutePreservesState(): void
  {
    $joinedAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $member = OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::MEMBER_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      'user-2',
      false,
      $joinedAt,
    );

    self::assertSame('user-2', $member->userId());
    self::assertFalse($member->isActive());
    self::assertSame($joinedAt, $member->joinedAt());
  }

  #[Test]
  public function testDeactivateAndActivateToggleState(): void
  {
    $member = OrganizationMember::join(
      OrganizationMemberId::fromString(self::MEMBER_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      'user-3',
    );

    $member->deactivate();
    self::assertFalse($member->isActive());

    $member->activate();
    self::assertTrue($member->isActive());
  }

  #[Test]
  public function testJoinedAtIsUtc(): void
  {
    $member = OrganizationMember::join(
      OrganizationMemberId::fromString(self::MEMBER_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      'user-4',
    );

    self::assertSame(
      new DateTimeZone('UTC')->getName(),
      $member->joinedAt()->getTimezone()->getName(),
    );
  }
}
