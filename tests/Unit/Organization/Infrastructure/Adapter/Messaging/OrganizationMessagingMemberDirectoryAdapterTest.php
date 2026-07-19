<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Adapter\Messaging;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use Organization\Infrastructure\Adapter\Messaging\OrganizationMessagingMemberDirectoryAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationMessagingMemberDirectoryAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationMessagingMemberDirectoryAdapter::class)]
final class OrganizationMessagingMemberDirectoryAdapterTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string USER_ID = 'user-1';

  #[Test]
  public function testResolveActiveMemberIdReturnsTheMemberIdWhenActive(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationAndUser')->willReturn($this->member(true));

    $adapter = new OrganizationMessagingMemberDirectoryAdapter($members);

    self::assertSame(self::MEMBER_ID, $adapter->resolveActiveMemberId(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testResolveActiveMemberIdReturnsNullWhenInactive(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationAndUser')->willReturn($this->member(false));

    $adapter = new OrganizationMessagingMemberDirectoryAdapter($members);

    self::assertNull($adapter->resolveActiveMemberId(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testMemberIsActiveRejectsAMemberFromAnotherOrganization(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member(true));

    $adapter = new OrganizationMessagingMemberDirectoryAdapter($members);

    self::assertFalse($adapter->memberIsActive('550e8400-e29b-41d4-a716-446655440099', self::MEMBER_ID));
  }

  #[Test]
  public function testMemberIsActiveRejectsAMalformedMemberIdWithoutThrowing(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);

    $adapter = new OrganizationMessagingMemberDirectoryAdapter($members);

    self::assertFalse($adapter->memberIsActive(self::ORG_ID, 'not-a-uuid'));
  }

  #[Test]
  public function testResolveUserIdForMemberReturnsTheUserId(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member(true));

    $adapter = new OrganizationMessagingMemberDirectoryAdapter($members);

    self::assertSame(self::USER_ID, $adapter->resolveUserIdForMember(self::ORG_ID, self::MEMBER_ID));
  }

  private function member(bool $isActive): OrganizationMember
  {
    $member = OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::MEMBER_ID),
      OrganizationId::fromString(self::ORG_ID),
      self::USER_ID,
      true,
      new DateTimeImmutable(),
    );

    if (!$isActive) {
      $member->deactivate();
    }

    return $member;
  }
}
