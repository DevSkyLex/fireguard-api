<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ListOrganizationMembers;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\ListOrganizationMembers\{ListOrganizationMembersHandler, ListOrganizationMembersQuery, ListOrganizationMembersResult};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListOrganizationMembersHandler::class)]
final class ListOrganizationMembersHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsMembersWithRoleIds(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440900';
    $memberId = '550e8400-e29b-41d4-a716-446655440901';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Lille'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-4 days'),
    );

    $member = OrganizationMember::reconstitute(
      id: new OrganizationMemberId($memberId),
      organizationId: new OrganizationId($organizationId),
      userId: '550e8400-e29b-41d4-a716-446655440902',
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
      ->with(self::callback(static fn (OrganizationId $id): bool => $organizationId === (string) $id))
      ->willReturn([$member]);
    $memberRepository->expects(self::once())
      ->method('findRoleIdsForMember')
      ->with(self::callback(static fn (OrganizationMemberId $id): bool => $memberId === (string) $id))
      ->willReturn(['550e8400-e29b-41d4-a716-446655440903']);

    $handler = new ListOrganizationMembersHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
    );

    $result = $handler->__invoke(new ListOrganizationMembersQuery($organizationId));

    self::assertInstanceOf(ListOrganizationMembersResult::class, $result);
    self::assertCount(1, $result->members);
    self::assertSame($memberId, $result->members[0]->id);
    self::assertSame($organizationId, $result->members[0]->organizationId);
    self::assertSame('550e8400-e29b-41d4-a716-446655440902', $result->members[0]->userId);
    self::assertSame(['550e8400-e29b-41d4-a716-446655440903'], $result->members[0]->roleIds);
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

    $handler = new ListOrganizationMembersHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new ListOrganizationMembersQuery('550e8400-e29b-41d4-a716-446655440900'));
  }
}
