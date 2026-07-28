<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Adapter\Compliance;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationRepositoryPort, PlanRepositoryPort};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, PlanId, PlanKey};
use Organization\Infrastructure\Adapter\Compliance\OrganizationExportEntitlementAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationExportEntitlementAdapter.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationExportEntitlementAdapter::class)]
final class OrganizationExportEntitlementAdapterTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441810';

  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function testResolvePlanKeyReturnsNullForAMalformedOrganizationId(): void
  {
    $adapter = $this->adapter($this->organization(true), null, null);

    self::assertNull($adapter->resolvePlanKey('not-a-uuid'));
    self::assertFalse($adapter->isExportEntitled('not-a-uuid'));
  }

  #[Test]
  public function testResolvePlanKeyReturnsNullForAnUnknownOrganization(): void
  {
    $adapter = $this->adapter(null, null, null);

    self::assertNull($adapter->resolvePlanKey(self::ORGANIZATION_ID));
  }

  #[Test]
  public function testResolvePlanKeyUsesTheAssignedPlan(): void
  {
    $adapter = $this->adapter($this->organization(true), $this->plan('pro'), $this->plan('free'));

    self::assertSame('pro', $adapter->resolvePlanKey(self::ORGANIZATION_ID));
  }

  #[Test]
  public function testResolvePlanKeyFallsBackToTheDefaultPlan(): void
  {
    $adapter = $this->adapter($this->organization(false), null, $this->plan('free'));

    self::assertSame('free', $adapter->resolvePlanKey(self::ORGANIZATION_ID));
  }

  #[Test]
  public function testResolvePlanKeyReturnsNullWhenNoPlanCanBeResolved(): void
  {
    $adapter = $this->adapter($this->organization(false), null, null);

    self::assertNull($adapter->resolvePlanKey(self::ORGANIZATION_ID));
  }

  #[Test]
  public function testEntitledPlanKeysUnlockTheExport(): void
  {
    foreach (['pro', 'max'] as $key) {
      $adapter = $this->adapter($this->organization(true), $this->plan($key), null);

      self::assertTrue($adapter->isExportEntitled(self::ORGANIZATION_ID));
    }
  }

  #[Test]
  public function testOtherPlanKeysDoNotUnlockTheExport(): void
  {
    $adapter = $this->adapter($this->organization(true), $this->plan('free'), null);

    self::assertFalse($adapter->isExportEntitled(self::ORGANIZATION_ID));
  }

  /**
   * Builds the adapter over repository stubs.
   */
  private function adapter(
    ?Organization $organization,
    ?Plan $planById,
    ?Plan $defaultPlan,
  ): OrganizationExportEntitlementAdapter {
    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);

    $planRepository = $this->createStub(PlanRepositoryPort::class);
    $planRepository->method('findById')->willReturn($planById);
    $planRepository->method('findDefault')->willReturn($defaultPlan);

    return new OrganizationExportEntitlementAdapter($organizationRepository, $planRepository);
  }

  /**
   * Reconstitutes an organization with or without an assigned plan.
   */
  private function organization(bool $withPlan): Organization
  {
    return Organization::reconstitute(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Acme'),
      createdByUserId: 'user-1',
      isActive: true,
      createdAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
      planId: $withPlan ? PlanId::fromString(self::PLAN_ID) : null,
    );
  }

  /**
   * Builds a plan carrying the given key.
   */
  private function plan(string $key): Plan
  {
    return Plan::create(
      id: PlanId::fromString(self::PLAN_ID),
      key: new PlanKey($key),
      name: 'Plan',
      limits: [],
    );
  }
}
