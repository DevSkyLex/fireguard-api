<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ListUserOrganizations;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\{ListUserOrganizationsHandler, ListUserOrganizationsQuery};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;

use function count;

#[CoversClass(ListUserOrganizationsHandler::class)]
final class ListUserOrganizationsHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsDistinctActiveCompaniesForUser(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655440800';
    $organizationId = '550e8400-e29b-41d4-a716-446655440801';

    $activeMembershipA = OrganizationMember::reconstitute(
      id: new OrganizationMemberId('550e8400-e29b-41d4-a716-446655440810'),
      organizationId: new OrganizationId($organizationId),
      userId: $userId,
      isActive: true,
      joinedAt: new DateTimeImmutable('-2 days'),
    );

    $activeMembershipDuplicate = OrganizationMember::reconstitute(
      id: new OrganizationMemberId('550e8400-e29b-41d4-a716-446655440811'),
      organizationId: new OrganizationId($organizationId),
      userId: $userId,
      isActive: true,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $inactiveMembership = OrganizationMember::reconstitute(
      id: new OrganizationMemberId('550e8400-e29b-41d4-a716-446655440812'),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655440899'),
      userId: $userId,
      isActive: false,
      joinedAt: new DateTimeImmutable('-1 day'),
    );

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard Nantes'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-10 days'),
    );

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByUserId')
      ->with($userId)
      ->willReturn([$activeMembershipA, $activeMembershipDuplicate, $inactiveMembership]);
    $memberRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(self::isInstanceOf(OrganizationId::class))
      ->willReturn(2);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findByIds')
      ->with(self::callback(static function (array $ids) use ($organizationId): bool {
        return 1 === count($ids)
          && $ids[0] instanceof OrganizationId
          && $organizationId === (string) $ids[0];
      }))
      ->willReturn([$organization]);

    $handler = new ListUserOrganizationsHandler(
      memberRepository: $memberRepository,
      organizationRepository: $organizationRepository,
    );

    $result = $handler->__invoke(new ListUserOrganizationsQuery($userId));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertSame($organizationId, $result->items[0]->id);
    self::assertSame('Fireguard Nantes', $result->items[0]->name);
    self::assertSame(2, $result->items[0]->memberCount);
    self::assertSame(1, $result->total);
    self::assertSame(1, $result->limit);
    self::assertSame(0, $result->offset);
  }

  #[Test]
  public function testInvokeReturnsEmptyListWhenNoActiveMemberships(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByUserId')
      ->willReturn([]);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findByIds');

    $handler = new ListUserOrganizationsHandler(
      memberRepository: $memberRepository,
      organizationRepository: $organizationRepository,
    );

    $result = $handler->__invoke(new ListUserOrganizationsQuery('550e8400-e29b-41d4-a716-446655440800'));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertSame([], $result->items);
    self::assertSame(0, $result->total);
    self::assertSame(20, $result->limit);
    self::assertSame(0, $result->offset);
  }
}
