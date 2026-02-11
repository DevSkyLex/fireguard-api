<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ListUserOrganizations;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\{ListUserOrganizationsHandler, ListUserOrganizationsQuery, ListUserOrganizationsResult};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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

    self::assertInstanceOf(ListUserOrganizationsResult::class, $result);
    self::assertCount(1, $result->organizations);
    self::assertSame($organizationId, $result->organizations[0]->id);
    self::assertSame('Fireguard Nantes', $result->organizations[0]->name);
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

    self::assertInstanceOf(ListUserOrganizationsResult::class, $result);
    self::assertSame([], $result->organizations);
  }
}
