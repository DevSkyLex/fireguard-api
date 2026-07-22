<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetNavigationCounters;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{InterventionStatisticsPort, NonConformityStatisticsPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetNavigationCounters\{GetNavigationCountersHandler, GetNavigationCountersQuery, GetNavigationCountersResult};
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test GetNavigationCountersHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetNavigationCountersHandler::class)]
final class GetNavigationCountersHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655443301';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655443302';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655443303';

  #[Test]
  public function testInvokeReturnsBothCountersWhenCallerHoldsBothPermissions(): void
  {
    $organization = $this->makeOrganization();
    $member = $this->makeActiveMember();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->willReturn($member);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var InterventionStatisticsPort&MockObject $interventionStatistics */
    $interventionStatistics = $this->createMock(InterventionStatisticsPort::class);
    $interventionStatistics->expects(self::once())
      ->method('countOverview')
      ->with(self::ORGANIZATION_ID, self::isInstanceOf(DateTimeImmutable::class))
      ->willReturn(['total' => 10, 'open' => 4, 'overdue' => 1]);

    /** @var NonConformityStatisticsPort&MockObject $nonConformityStatistics */
    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::once())
      ->method('countNonConformitiesByStatus')
      ->with(self::ORGANIZATION_ID)
      ->willReturn(['open' => 3, 'in_progress' => 2, 'done' => 5, 'waived' => 1]);

    $handler = new GetNavigationCountersHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      authorization: $authorization,
      interventionStatistics: $interventionStatistics,
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetNavigationCountersQuery(self::ORGANIZATION_ID, self::USER_ID));

    self::assertInstanceOf(GetNavigationCountersResult::class, $result);
    self::assertSame(4, $result->openInterventions);
    self::assertSame(5, $result->openNonConformities);
  }

  #[Test]
  public function testInvokeReturnsZeroCountersWhenCallerLacksBothPermissions(): void
  {
    $organization = $this->makeOrganization();
    $member = $this->makeActiveMember();

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('findByOrganizationAndUser')->willReturn($member);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    /** @var InterventionStatisticsPort&MockObject $interventionStatistics */
    $interventionStatistics = $this->createMock(InterventionStatisticsPort::class);
    $interventionStatistics->expects(self::never())->method('countOverview');

    /** @var NonConformityStatisticsPort&MockObject $nonConformityStatistics */
    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesByStatus');

    $handler = new GetNavigationCountersHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      authorization: $authorization,
      interventionStatistics: $interventionStatistics,
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetNavigationCountersQuery(self::ORGANIZATION_ID, self::USER_ID));

    self::assertSame(0, $result->openInterventions);
    self::assertSame(0, $result->openNonConformities);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new GetNavigationCountersHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $this->createStub(OrganizationMemberRepositoryPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      interventionStatistics: $this->createStub(InterventionStatisticsPort::class),
      nonConformityStatistics: $this->createStub(NonConformityStatisticsPort::class),
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new GetNavigationCountersQuery(self::ORGANIZATION_ID, self::USER_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenCallerHasNoActiveMembership(): void
  {
    $organization = $this->makeOrganization();

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationAndUser')
      ->willReturn(null);

    $handler = new GetNavigationCountersHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      interventionStatistics: $this->createStub(InterventionStatisticsPort::class),
      nonConformityStatistics: $this->createStub(NonConformityStatisticsPort::class),
    );

    $this->expectException(OrganizationMemberNotFoundException::class);

    $handler->__invoke(new GetNavigationCountersQuery(self::ORGANIZATION_ID, self::USER_ID));
  }

  private function makeOrganization(): Organization
  {
    return Organization::reconstitute(
      id: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Bordeaux'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-10 days'),
    );
  }

  private function makeActiveMember(): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      id: new OrganizationMemberId(self::MEMBER_ID),
      organizationId: new OrganizationId(self::ORGANIZATION_ID),
      userId: self::USER_ID,
      isActive: true,
      joinedAt: new DateTimeImmutable('-5 days'),
    );
  }
}
