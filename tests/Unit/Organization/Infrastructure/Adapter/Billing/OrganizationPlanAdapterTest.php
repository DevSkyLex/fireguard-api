<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Adapter\Billing;

use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationSlug, PlanId, PlanKey};
use Organization\Infrastructure\Adapter\Billing\OrganizationPlanAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationPlanAdapter::class)]
final class OrganizationPlanAdapterTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function testFindCurrentPlanReturnsTheOrganizationsAssignedPlan(): void
  {
    $plan = $this->plan('pro', 'Pro');

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn(
      $this->organizationWithPlan(PlanId::fromString(self::PLAN_ID)),
    );

    $planRepository = $this->createMock(PlanRepositoryPort::class);
    $planRepository->expects(self::once())->method('findById')->willReturn($plan);
    $planRepository->expects(self::never())->method('findDefault');

    $adapter = new OrganizationPlanAdapter($organizationRepository, $planRepository);

    $summary = $adapter->findCurrentPlan(self::ORGANIZATION_ID);

    self::assertNotNull($summary);
    self::assertSame('pro', $summary->key);
    self::assertSame('Pro', $summary->name);
  }

  #[Test]
  public function testFindCurrentPlanFallsBackToTheCatalogDefaultWhenUnassigned(): void
  {
    $defaultPlan = $this->plan('free', 'Free');

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($this->organizationWithPlan(null));

    $planRepository = $this->createMock(PlanRepositoryPort::class);
    $planRepository->expects(self::never())->method('findById');
    $planRepository->expects(self::once())->method('findDefault')->willReturn($defaultPlan);

    $adapter = new OrganizationPlanAdapter($organizationRepository, $planRepository);

    $summary = $adapter->findCurrentPlan(self::ORGANIZATION_ID);

    self::assertNotNull($summary);
    self::assertSame('free', $summary->key);
  }

  #[Test]
  public function testFindCurrentPlanReturnsNullWhenTheOrganizationDoesNotExist(): void
  {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn(null);

    $planRepository = $this->createMock(PlanRepositoryPort::class);
    $planRepository->expects(self::never())->method('findById');
    $planRepository->expects(self::never())->method('findDefault');

    $adapter = new OrganizationPlanAdapter($organizationRepository, $planRepository);

    self::assertNull($adapter->findCurrentPlan(self::ORGANIZATION_ID));
  }

  #[Test]
  public function testFindCurrentPlanReturnsNullForAMalformedOrganizationId(): void
  {
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    $planRepository = $this->createMock(PlanRepositoryPort::class);
    $planRepository->expects(self::never())->method('findById');
    $planRepository->expects(self::never())->method('findDefault');

    $adapter = new OrganizationPlanAdapter($organizationRepository, $planRepository);

    self::assertNull($adapter->findCurrentPlan('not-a-uuid'));
  }

  private function plan(string $key, string $name): Plan
  {
    return Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey($key),
      name: $name,
      limits: ['members' => 50],
    );
  }

  private function organizationWithPlan(?PlanId $planId): Organization
  {
    return Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Acme'),
      slug: new OrganizationSlug('acme'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655440099',
      planId: $planId,
    );
  }
}
