<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{
  EquipmentStatisticsPort,
  FacilityStatisticsPort,
  InspectionStatisticsPort,
  OrganizationInvitationRepositoryPort,
  OrganizationMemberRepositoryPort,
  OrganizationQuotaLockPort,
  OrganizationRepositoryPort,
  PlanRepositoryPort
};
use Organization\Application\Service\OrganizationQuotaService;
use Organization\Domain\Exception\OrganizationQuotaExceededException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationQuotaResource, PlanId, PlanKey};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationQuotaService::class)]
final class OrganizationQuotaServiceTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string PLAN_ID = '22222222-2222-4222-8222-222222222221';

  #[Test]
  public function testGetLimitAndUsagePerResource(): void
  {
    $service = $this->service(
      plan: $this->plan(['members' => 5, 'facilities' => 2]),
      activeMemberCount: 3,
      facilityCount: 1,
      equipmentCount: 40,
      inspectionCount: 0,
    );

    self::assertSame(5, $service->getLimit(self::ORGANIZATION_ID, OrganizationQuotaResource::MEMBERS));
    self::assertSame(2, $service->getLimit(self::ORGANIZATION_ID, OrganizationQuotaResource::FACILITIES));
    self::assertNull($service->getLimit(self::ORGANIZATION_ID, OrganizationQuotaResource::EQUIPMENT));

    self::assertSame(3, $service->getUsage(self::ORGANIZATION_ID, OrganizationQuotaResource::MEMBERS));
    self::assertSame(1, $service->getUsage(self::ORGANIZATION_ID, OrganizationQuotaResource::FACILITIES));
    self::assertSame(40, $service->getUsage(self::ORGANIZATION_ID, OrganizationQuotaResource::EQUIPMENT));
    self::assertSame(0, $service->getUsage(self::ORGANIZATION_ID, OrganizationQuotaResource::INSPECTIONS));
  }

  #[Test]
  public function testMemberUsageCountsActiveMembersPlusPendingInvitations(): void
  {
    $service = $this->service(
      plan: $this->plan(['members' => 10]),
      activeMemberCount: 4,
      pendingInvitationCount: 3,
    );

    self::assertSame(7, $service->getUsage(self::ORGANIZATION_ID, OrganizationQuotaResource::MEMBERS));
  }

  #[Test]
  public function testAssertCanAddPassesUnderLimit(): void
  {
    $service = $this->service(plan: $this->plan(['members' => 5]), activeMemberCount: 4);

    $service->assertCanAdd(self::ORGANIZATION_ID, OrganizationQuotaResource::MEMBERS);

    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testAssertCanAddCountsPendingInvitationsTowardMemberCap(): void
  {
    // 3 active + 2 pending = 5 already occupies the cap of 5.
    $service = $this->service(
      plan: $this->plan(['members' => 5]),
      activeMemberCount: 3,
      pendingInvitationCount: 2,
    );

    $this->expectException(OrganizationQuotaExceededException::class);

    $service->assertCanAdd(self::ORGANIZATION_ID, OrganizationQuotaResource::MEMBERS);
  }

  #[Test]
  public function testAssertCanAddThrowsAtLimit(): void
  {
    $service = $this->service(plan: $this->plan(['members' => 5]), activeMemberCount: 5);

    $this->expectException(OrganizationQuotaExceededException::class);

    $service->assertCanAdd(self::ORGANIZATION_ID, OrganizationQuotaResource::MEMBERS);
  }

  #[Test]
  public function testAssertCanAddPassesWhenUnlimited(): void
  {
    $service = $this->service(plan: $this->plan([]), facilityCount: 9999);

    $service->assertCanAdd(self::ORGANIZATION_ID, OrganizationQuotaResource::FACILITIES);

    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testAssertCanAcceptMemberIgnoresPendingInvitationBeingAccepted(): void
  {
    // 4 active + 3 pending: getUsage would be 7 (> 5), but acceptance only counts
    // active members, so the reserved pending slot never blocks its own acceptance.
    $service = $this->service(
      plan: $this->plan(['members' => 5]),
      activeMemberCount: 4,
      pendingInvitationCount: 3,
    );

    $service->assertCanAcceptMember(self::ORGANIZATION_ID);

    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testAssertCanAcceptMemberThrowsWhenActiveMembersAtLimit(): void
  {
    // Plan-downgrade case: already 5 active members against a cap of 5.
    $service = $this->service(plan: $this->plan(['members' => 5]), activeMemberCount: 5);

    $this->expectException(OrganizationQuotaExceededException::class);

    $service->assertCanAcceptMember(self::ORGANIZATION_ID);
  }

  #[Test]
  public function testAssertCanAcceptMemberPassesWhenUnlimited(): void
  {
    $service = $this->service(plan: $this->plan([]), activeMemberCount: 9999);

    $service->assertCanAcceptMember(self::ORGANIZATION_ID);

    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testGetQuotaSummaryCoversEveryResource(): void
  {
    $service = $this->service(
      plan: $this->plan(['members' => 5, 'facilities' => 2, 'equipment' => 50, 'inspections' => 100]),
      activeMemberCount: 3,
      facilityCount: 2,
      equipmentCount: 10,
      inspectionCount: 7,
    );

    $summary = $service->getQuotaSummary(self::ORGANIZATION_ID);

    self::assertSame(
      [
        ['resource' => 'members', 'used' => 3, 'limit' => 5],
        ['resource' => 'facilities', 'used' => 2, 'limit' => 2],
        ['resource' => 'equipment', 'used' => 10, 'limit' => 50],
        ['resource' => 'inspections', 'used' => 7, 'limit' => 100],
      ],
      $summary,
    );
  }

  #[Test]
  public function testPlanResolutionIsCachedInMemory(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($this->organization());

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($this->plan(['members' => 5]));

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(2);

    $invitationRepository = $this->createStub(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countPendingByOrganizationId')->willReturn(0);

    $service = new OrganizationQuotaService(
      $organizationRepository,
      $planRepository,
      $memberRepository,
      $invitationRepository,
      $this->createStub(FacilityStatisticsPort::class),
      $this->createStub(EquipmentStatisticsPort::class),
      $this->createStub(InspectionStatisticsPort::class),
      $this->createStub(OrganizationQuotaLockPort::class),
    );

    self::assertSame(5, $service->getLimit(self::ORGANIZATION_ID, OrganizationQuotaResource::MEMBERS));
    self::assertSame(2, $service->getUsage(self::ORGANIZATION_ID, OrganizationQuotaResource::MEMBERS));
  }

  #[Test]
  public function testAssertCanAddAcquiresTheAdvisoryLockWhenLimited(): void
  {
    /** @var OrganizationQuotaLockPort&MockObject $quotaLock */
    $quotaLock = $this->createMock(OrganizationQuotaLockPort::class);
    $quotaLock->expects(self::once())
      ->method('acquire')
      ->with(self::ORGANIZATION_ID, OrganizationQuotaResource::FACILITIES);

    $service = $this->service(plan: $this->plan(['facilities' => 5]), facilityCount: 2, quotaLock: $quotaLock);

    $service->assertCanAdd(self::ORGANIZATION_ID, OrganizationQuotaResource::FACILITIES);
  }

  #[Test]
  public function testAssertCanAddSkipsTheLockWhenUnlimited(): void
  {
    /** @var OrganizationQuotaLockPort&MockObject $quotaLock */
    $quotaLock = $this->createMock(OrganizationQuotaLockPort::class);
    $quotaLock->expects(self::never())->method('acquire');

    $service = $this->service(plan: $this->plan([]), facilityCount: 9999, quotaLock: $quotaLock);

    $service->assertCanAdd(self::ORGANIZATION_ID, OrganizationQuotaResource::FACILITIES);

    $this->addToAssertionCount(1);
  }

  private function service(
    Plan $plan,
    int $activeMemberCount = 0,
    int $pendingInvitationCount = 0,
    int $facilityCount = 0,
    int $equipmentCount = 0,
    int $inspectionCount = 0,
    ?OrganizationQuotaLockPort $quotaLock = null,
  ): OrganizationQuotaService {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organization());

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($plan);

    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countActiveByOrganizationId')->willReturn($activeMemberCount);

    $invitationRepository = $this->createStub(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countPendingByOrganizationId')->willReturn($pendingInvitationCount);

    $facilityStatistics = $this->createStub(FacilityStatisticsPort::class);
    $facilityStatistics->method('countActiveFacilities')->willReturn($facilityCount);

    $equipmentStatistics = $this->createStub(EquipmentStatisticsPort::class);
    $equipmentStatistics->method('countActiveEquipment')->willReturn($equipmentCount);

    $inspectionStatistics = $this->createStub(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countActiveInspections')->willReturn($inspectionCount);

    return new OrganizationQuotaService(
      $organizationRepository,
      $planRepository,
      $memberRepository,
      $invitationRepository,
      $facilityStatistics,
      $equipmentStatistics,
      $inspectionStatistics,
      $quotaLock ?? $this->createStub(OrganizationQuotaLockPort::class),
    );
  }

  private function organization(): Organization
  {
    return Organization::reconstitute(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Test'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      planId: PlanId::fromString(self::PLAN_ID),
    );
  }

  /**
   * @param array<string, int> $limits
   */
  private function plan(array $limits): Plan
  {
    return Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey('pro'),
      name: 'Pro',
      limits: $limits,
    );
  }
}
