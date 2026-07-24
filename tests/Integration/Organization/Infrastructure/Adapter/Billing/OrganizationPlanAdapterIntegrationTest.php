<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\Adapter\Billing;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use Organization\Infrastructure\Adapter\Billing\OrganizationPlanAdapter;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Organization\Infrastructure\Persistence\Doctrine\Repository\{OrganizationRepository, PlanRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test OrganizationPlanAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OrganizationPlanAdapter::class)]
final class OrganizationPlanAdapterIntegrationTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  private PlanRepository $planRepository;

  private OrganizationPlanAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->planRepository = new PlanRepository($this->entityManager);
    $this->adapter = new OrganizationPlanAdapter(
      new OrganizationRepository($this->entityManager),
      $this->planRepository,
    );
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testFindCurrentPlanResolvesExplicitlyAssignedPlan(): void
  {
    $this->planRepository->save($this->plan(
      id: 'f3000000-0000-4000-8000-000000000001',
      key: 'plan-adapter-explicit',
      name: 'Explicit Plan',
    ));
    $this->entityManager->persist($this->organization(
      id: 'f2000000-0000-4000-8000-000000000001',
      slug: 'plan-adapter-explicit-org',
      planId: 'f3000000-0000-4000-8000-000000000001',
    ));
    $this->entityManager->flush();
    $this->entityManager->clear();

    $summary = $this->adapter->findCurrentPlan('f2000000-0000-4000-8000-000000000001');

    self::assertNotNull($summary);
    self::assertSame('plan-adapter-explicit', $summary->key);
    self::assertSame('Explicit Plan', $summary->name);
  }

  #[Test]
  public function testFindCurrentPlanFallsBackToCatalogDefaultWhenUnassigned(): void
  {
    // sortOrder 0 makes this the winning default over any seeded catalog default.
    $this->planRepository->save($this->plan(
      id: 'f3000000-0000-4000-8000-000000000011',
      key: 'plan-adapter-default',
      name: 'Adapter Default',
      isDefault: true,
      sortOrder: 0,
    ));
    $this->entityManager->persist($this->organization(
      id: 'f2000000-0000-4000-8000-000000000011',
      slug: 'plan-adapter-unassigned-org',
      planId: null,
    ));
    $this->entityManager->flush();
    $this->entityManager->clear();

    $summary = $this->adapter->findCurrentPlan('f2000000-0000-4000-8000-000000000011');

    self::assertNotNull($summary);
    self::assertSame('plan-adapter-default', $summary->key);
  }

  #[Test]
  public function testFindCurrentPlanReturnsNullForMalformedOrganizationId(): void
  {
    self::assertNull($this->adapter->findCurrentPlan('not-a-valid-uuid'));
  }

  #[Test]
  public function testFindCurrentPlanReturnsNullForUnknownOrganization(): void
  {
    self::assertNull($this->adapter->findCurrentPlan('f2000000-0000-4000-8000-0000000000ff'));
  }

  private function plan(
    string $id,
    string $key,
    string $name,
    bool $isDefault = false,
    int $sortOrder = 5,
  ): Plan {
    return Plan::create(
      id: PlanId::fromString($id),
      key: new PlanKey($key),
      name: $name,
      limits: [],
      isActive: true,
      isDefault: $isDefault,
      sortOrder: $sortOrder,
    );
  }

  private function organization(string $id, string $slug, ?string $planId): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Org ' . $slug;
    $organization->slug = $slug;
    $organization->ownerUserId = 'f2000000-0000-4000-8000-0000000000aa';
    $organization->createdByUserId = 'f2000000-0000-4000-8000-0000000000aa';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->planId = $planId;
    $organization->createdAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
    $organization->updatedAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    return $organization;
  }
}
