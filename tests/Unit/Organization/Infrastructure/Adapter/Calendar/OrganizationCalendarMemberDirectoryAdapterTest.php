<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Adapter\Calendar;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use Organization\Infrastructure\Adapter\Calendar\OrganizationCalendarMemberDirectoryAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationCalendarMemberDirectoryAdapter.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationCalendarMemberDirectoryAdapter::class)]
final class OrganizationCalendarMemberDirectoryAdapterTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441810';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655441811';

  #[Test]
  public function testResolvesTheIdentifierOfAnActiveMember(): void
  {
    $adapter = new OrganizationCalendarMemberDirectoryAdapter($this->repositoryReturning($this->member(true)));

    self::assertSame(self::MEMBER_ID, $adapter->resolveActiveMemberId(self::ORGANIZATION_ID, 'user-1'));
  }

  #[Test]
  public function testReturnsNullForAnInactiveMember(): void
  {
    $adapter = new OrganizationCalendarMemberDirectoryAdapter($this->repositoryReturning($this->member(false)));

    self::assertNull($adapter->resolveActiveMemberId(self::ORGANIZATION_ID, 'user-1'));
  }

  #[Test]
  public function testReturnsNullWhenNoMembershipExists(): void
  {
    $adapter = new OrganizationCalendarMemberDirectoryAdapter($this->repositoryReturning(null));

    self::assertNull($adapter->resolveActiveMemberId(self::ORGANIZATION_ID, 'user-1'));
  }

  #[Test]
  public function testReturnsNullForAMalformedOrganizationId(): void
  {
    $adapter = new OrganizationCalendarMemberDirectoryAdapter($this->repositoryReturning($this->member(true)));

    self::assertNull($adapter->resolveActiveMemberId('not-a-uuid', 'user-1'));
  }

  /**
   * Builds a member repository stub returning the given member.
   */
  private function repositoryReturning(?OrganizationMember $member): OrganizationMemberRepositoryPort
  {
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($member);

    return $repository;
  }

  /**
   * Reconstitutes a membership with the given activation flag.
   */
  private function member(bool $isActive): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      id: OrganizationMemberId::fromString(self::MEMBER_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      userId: 'user-1',
      isActive: $isActive,
      joinedAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
    );
  }
}
