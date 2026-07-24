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
use Shared\Application\Port\Inbound\QueryBusPort;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\GetUserResult;

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

    $adapter = new OrganizationMessagingMemberDirectoryAdapter($members, $this->createStub(QueryBusPort::class));

    self::assertSame(self::MEMBER_ID, $adapter->resolveActiveMemberId(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testResolveActiveMemberIdReturnsNullWhenInactive(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationAndUser')->willReturn($this->member(false));

    $adapter = new OrganizationMessagingMemberDirectoryAdapter($members, $this->createStub(QueryBusPort::class));

    self::assertNull($adapter->resolveActiveMemberId(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testMemberIsActiveRejectsAMemberFromAnotherOrganization(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member(true));

    $adapter = new OrganizationMessagingMemberDirectoryAdapter($members, $this->createStub(QueryBusPort::class));

    self::assertFalse($adapter->memberIsActive('550e8400-e29b-41d4-a716-446655440099', self::MEMBER_ID));
  }

  #[Test]
  public function testMemberIsActiveRejectsAMalformedMemberIdWithoutThrowing(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);

    $adapter = new OrganizationMessagingMemberDirectoryAdapter($members, $this->createStub(QueryBusPort::class));

    self::assertFalse($adapter->memberIsActive(self::ORG_ID, 'not-a-uuid'));
  }

  #[Test]
  public function testResolveUserIdForMemberReturnsTheUserId(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member(true));

    $adapter = new OrganizationMessagingMemberDirectoryAdapter($members, $this->createStub(QueryBusPort::class));

    self::assertSame(self::USER_ID, $adapter->resolveUserIdForMember(self::ORG_ID, self::MEMBER_ID));
  }

  #[Test]
  public function testDisplayNamesForResolvesNamesForTheRequestedMembers(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationId')->willReturn([$this->member(true)]);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetUserResult($this->user('Amelie', 'Rousseau')));

    $adapter = new OrganizationMessagingMemberDirectoryAdapter($members, $queryBus);

    self::assertSame(
      [self::MEMBER_ID => 'Amelie Rousseau'],
      $adapter->displayNamesFor(self::ORG_ID, [self::MEMBER_ID]),
    );
  }

  #[Test]
  public function testDisplayNamesForOmitsAMemberWhoseUserCannotBeResolved(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationId')->willReturn([$this->member(true)]);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetUserResult(null));

    $adapter = new OrganizationMessagingMemberDirectoryAdapter($members, $queryBus);

    // Absent from the map, never rendered as its identifier.
    self::assertSame([], $adapter->displayNamesFor(self::ORG_ID, [self::MEMBER_ID]));
  }

  #[Test]
  public function testDisplayNamesForShortCircuitsOnAnEmptyRequest(): void
  {
    $members = $this->createMock(OrganizationMemberRepositoryPort::class);
    $members->expects(self::never())->method('findByOrganizationId');

    $adapter = new OrganizationMessagingMemberDirectoryAdapter($members, $this->createStub(QueryBusPort::class));

    self::assertSame([], $adapter->displayNamesFor(self::ORG_ID, []));
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

  private function user(string $firstName, string $lastName): UserView
  {
    return new UserView(
      self::USER_ID,
      'amelie',
      'amelie@example.test',
      $firstName,
      $lastName,
      null,
      'active',
      true,
      null,
      new DateTimeImmutable(),
      null,
      true,
    );
  }
}
