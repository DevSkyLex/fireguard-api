<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ListOrganizationMembers;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\ListOrganizationMembers\{ListOrganizationMembersHandler, ListOrganizationMembersQuery};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName, OrganizationRoleId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Domain\Exception\InvalidValueException;

use function array_filter;
use function count;

#[CoversClass(ListOrganizationMembersHandler::class)]
final class ListOrganizationMembersHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440900';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655440901';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440902';

  private const string ROLE_ID = '550e8400-e29b-41d4-a716-446655440903';

  #[Test]
  public function testInvokeReturnsMembersWithRoleIds(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Lille'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-4 days'),
    );

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      userId: self::USER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-2 days'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(
        self::callback(static fn (OrganizationId $id): bool => self::ORG_ID === (string) $id),
        null,
        null,
        null,
        self::callback(static fn (Sorting $sorting): bool => 'joinedAt' === $sorting->field && SortDirection::ASC === $sorting->direction),
        20,
        0,
      )
      ->willReturn([$member]);
    $memberRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(
        self::callback(static fn (OrganizationId $id): bool => self::ORG_ID === (string) $id),
        null,
        null,
        null,
      )
      ->willReturn(1);
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->with(self::callback(static fn (OrganizationMemberId $id): bool => self::MEMBER_ID === (string) $id))
      ->willReturn([self::ROLE_ID]);

    $handler = new ListOrganizationMembersHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
    );

    $result = $handler->__invoke(new ListOrganizationMembersQuery(self::ORG_ID));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertSame(self::MEMBER_ID, $result->items[0]->id);
    self::assertSame(self::ORG_ID, $result->items[0]->organizationId);
    self::assertSame(self::USER_ID, $result->items[0]->userId);
    self::assertSame([self::ROLE_ID], $result->items[0]->roleIds);
    self::assertSame(1, $result->total);
    self::assertSame(20, $result->limit);
    self::assertSame(0, $result->offset);
  }

  #[Test]
  public function testInvokePassesSearchStatusRoleAndSortingFiltersToTheRepository(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Lille'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-4 days'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(
        self::callback(static fn (OrganizationId $id): bool => self::ORG_ID === (string) $id),
        'jane',
        true,
        self::callback(static fn (OrganizationRoleId $id): bool => self::ROLE_ID === (string) $id),
        self::callback(static fn (Sorting $sorting): bool => 'displayName' === $sorting->field && SortDirection::DESC === $sorting->direction),
        10,
        5,
      )
      ->willReturn([]);
    $memberRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(
        self::callback(static fn (OrganizationId $id): bool => self::ORG_ID === (string) $id),
        'jane',
        true,
        self::callback(static fn (OrganizationRoleId $id): bool => self::ROLE_ID === (string) $id),
      )
      ->willReturn(0);

    $handler = new ListOrganizationMembersHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
    );

    $result = $handler->__invoke(new ListOrganizationMembersQuery(
      organizationId: self::ORG_ID,
      pagination: new Pagination(offset: 5, limit: 10),
      search: 'jane',
      status: 'active',
      roleId: self::ROLE_ID,
      sorting: new Sorting('displayName', SortDirection::DESC),
    ));

    self::assertSame([], $result->items);
    self::assertSame(0, $result->total);
    self::assertSame(10, $result->limit);
    self::assertSame(5, $result->offset);
  }

  #[Test]
  public function testInvokeMapsInactiveStatusFilterToFalse(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Lille'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-4 days'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(self::anything(), null, false, null, self::anything(), self::anything(), self::anything())
      ->willReturn([]);
    $memberRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(self::anything(), null, false, null)
      ->willReturn(0);

    $handler = new ListOrganizationMembersHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
    );

    $handler->__invoke(new ListOrganizationMembersQuery(self::ORG_ID, status: 'inactive'));
  }

  #[Test]
  public function testInvokeTreatsAllStatusAsNoFilter(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Lille'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-4 days'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(self::anything(), null, null, null, self::anything(), self::anything(), self::anything())
      ->willReturn([]);
    $memberRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(self::anything(), null, null, null)
      ->willReturn(0);

    $handler = new ListOrganizationMembersHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
    );

    $handler->__invoke(new ListOrganizationMembersQuery(self::ORG_ID, status: 'all'));
  }

  #[Test]
  public function testInvokeThrowsOnAnInvalidStatusFilter(): void
  {
    $organization = Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Lille'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-4 days'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('findByOrganizationId');
    $memberRepository->expects(self::never())->method('countByOrganizationId');

    $handler = new ListOrganizationMembersHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new ListOrganizationMembersQuery(self::ORG_ID, status: 'bogus'));
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('findByOrganizationId');
    $memberRepository->expects(self::never())->method('countByOrganizationId');

    $handler = new ListOrganizationMembersHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new ListOrganizationMembersQuery(self::ORG_ID));
  }

  #[Test]
  public function testInvokeFlagsOnlyTheOrganizationOwnerAmongMultipleMembers(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440910';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655440911';
    $otherUserId = '550e8400-e29b-41d4-a716-446655440912';
    $ownerMemberId = '550e8400-e29b-41d4-a716-446655440913';
    $otherMemberId = '550e8400-e29b-41d4-a716-446655440914';

    // The owner is resolved from the already-loaded organization aggregate
    // (a single read), never from a per-member lookup.
    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Nice'),
      createdByUserId: $ownerUserId,
      isActive: true,
      createdAt: new DateTimeImmutable('-10 days'),
      ownerUserId: $ownerUserId,
    );

    $ownerMember = OrganizationMember::reconstitute(
      id: new OrganizationMemberId($ownerMemberId),
      organizationId: new OrganizationId($organizationId),
      userId: $ownerUserId,
      isActive: true,
      joinedAt: new DateTimeImmutable('-10 days'),
    );
    $otherMember = OrganizationMember::reconstitute(
      id: new OrganizationMemberId($otherMemberId),
      organizationId: new OrganizationId($organizationId),
      userId: $otherUserId,
      isActive: true,
      joinedAt: new DateTimeImmutable('-2 days'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([$ownerMember, $otherMember]);
    $memberRepository->method('countByOrganizationId')->willReturn(2);
    $memberRepository->method('findRoleIdsForMember')->willReturn([]);

    $handler = new ListOrganizationMembersHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
    );

    $result = $handler->__invoke(new ListOrganizationMembersQuery($organizationId));

    self::assertCount(2, $result->items);

    $isOwnerByUserId = [];
    foreach ($result->items as $item) {
      $isOwnerByUserId[$item->userId] = $item->isOwner;
    }

    self::assertTrue($isOwnerByUserId[$ownerUserId]);
    self::assertFalse($isOwnerByUserId[$otherUserId]);
    self::assertSame(1, count(array_filter($isOwnerByUserId)));
  }
}
