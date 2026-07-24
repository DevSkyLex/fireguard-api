<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use Organization\Infrastructure\Persistence\Doctrine\Repository\PlanRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;

/**
 * Test PlanRepository.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: PlanRepository::class)]
final class PlanRepositoryIntegrationTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  private PlanRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->repository = new PlanRepository($this->entityManager);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveAndFindByIdRoundTripsAggregate(): void
  {
    $plan = Plan::create(
      id: PlanId::fromString('f1000000-0000-4000-8000-000000000001'),
      key: new PlanKey('plan-repo-roundtrip'),
      name: 'Round Trip',
      limits: ['members' => 12, 'facilities' => 4],
      description: 'Round trip fixture',
      isActive: true,
      isDefault: false,
      sortOrder: 42,
    );

    $this->repository->save($plan);
    $this->entityManager->clear();

    $found = $this->repository->findById(PlanId::fromString('f1000000-0000-4000-8000-000000000001'));

    self::assertNotNull($found);
    self::assertSame('plan-repo-roundtrip', (string) $found->key());
    self::assertSame('Round Trip', $found->name());
    self::assertSame('Round trip fixture', $found->description());
    self::assertSame(['members' => 12, 'facilities' => 4], $found->limits());
    self::assertSame(42, $found->sortOrder());
    self::assertTrue($found->isActive());
  }

  #[Test]
  public function testFindByIdReturnsNullWhenAbsent(): void
  {
    self::assertNull($this->repository->findById(PlanId::fromString('f1000000-0000-4000-8000-0000000000ff')));
  }

  #[Test]
  public function testFindByKeyReturnsMatchOrNull(): void
  {
    $plan = Plan::create(
      id: PlanId::fromString('f1000000-0000-4000-8000-000000000011'),
      key: new PlanKey('plan-repo-bykey'),
      name: 'By Key',
      limits: [],
    );
    $this->repository->save($plan);
    $this->entityManager->clear();

    $found = $this->repository->findByKey(new PlanKey('plan-repo-bykey'));
    $missing = $this->repository->findByKey(new PlanKey('plan-repo-missing-key'));

    self::assertNotNull($found);
    self::assertSame('f1000000-0000-4000-8000-000000000011', (string) $found->id());
    self::assertNull($missing);
  }

  #[Test]
  public function testSaveUpdatesExistingRecordInPlace(): void
  {
    $plan = Plan::create(
      id: PlanId::fromString('f1000000-0000-4000-8000-000000000021'),
      key: new PlanKey('plan-repo-update'),
      name: 'Before Rename',
      limits: ['members' => 5],
    );
    $this->repository->save($plan);

    $loaded = $this->repository->findById(PlanId::fromString('f1000000-0000-4000-8000-000000000021'));
    self::assertNotNull($loaded);
    $loaded->rename('After Rename', 'Updated description');
    $loaded->changeLimits(['members' => 9, 'inspections' => 500]);
    $this->repository->save($loaded);
    $this->entityManager->clear();

    $reloaded = $this->repository->findById(PlanId::fromString('f1000000-0000-4000-8000-000000000021'));

    self::assertNotNull($reloaded);
    self::assertSame('After Rename', $reloaded->name());
    self::assertSame('Updated description', $reloaded->description());
    self::assertSame(['members' => 9, 'inspections' => 500], $reloaded->limits());
    // The key is stable across an update: no duplicate row is created.
    self::assertSame('plan-repo-update', (string) $reloaded->key());
  }

  #[Test]
  public function testDeleteRemovesPlan(): void
  {
    $plan = Plan::create(
      id: PlanId::fromString('f1000000-0000-4000-8000-000000000031'),
      key: new PlanKey('plan-repo-delete'),
      name: 'To Delete',
      limits: [],
    );
    $this->repository->save($plan);
    self::assertNotNull($this->repository->findById(PlanId::fromString('f1000000-0000-4000-8000-000000000031')));

    $this->repository->delete(PlanId::fromString('f1000000-0000-4000-8000-000000000031'));
    $this->entityManager->clear();

    self::assertNull($this->repository->findById(PlanId::fromString('f1000000-0000-4000-8000-000000000031')));
  }

  #[Test]
  public function testFindAllActiveIncludesActiveAndExcludesInactive(): void
  {
    $activePlan = Plan::create(
      id: PlanId::fromString('f1000000-0000-4000-8000-000000000041'),
      key: new PlanKey('plan-repo-active'),
      name: 'Active Plan',
      limits: [],
      isActive: true,
    );
    $inactivePlan = Plan::create(
      id: PlanId::fromString('f1000000-0000-4000-8000-000000000042'),
      key: new PlanKey('plan-repo-inactive'),
      name: 'Inactive Plan',
      limits: [],
      isActive: false,
    );
    $this->repository->save($activePlan);
    $this->repository->save($inactivePlan);
    $this->entityManager->clear();

    $activeIds = array_map(static fn (Plan $plan): string => (string) $plan->id(), $this->repository->findAllActive());

    self::assertContains('f1000000-0000-4000-8000-000000000041', $activeIds);
    self::assertNotContains('f1000000-0000-4000-8000-000000000042', $activeIds);
  }

  #[Test]
  public function testFindAllOrdersOwnPlansBySortOrder(): void
  {
    $second = Plan::create(
      id: PlanId::fromString('f1000000-0000-4000-8000-000000000051'),
      key: new PlanKey('plan-repo-order-b'),
      name: 'Order B',
      limits: [],
      sortOrder: 920,
    );
    $first = Plan::create(
      id: PlanId::fromString('f1000000-0000-4000-8000-000000000052'),
      key: new PlanKey('plan-repo-order-a'),
      name: 'Order A',
      limits: [],
      sortOrder: 910,
    );
    $this->repository->save($second);
    $this->repository->save($first);
    $this->entityManager->clear();

    $ownIds = ['f1000000-0000-4000-8000-000000000052', 'f1000000-0000-4000-8000-000000000051'];
    $orderedOwn = array_values(array_filter(
      array_map(static fn (Plan $plan): string => (string) $plan->id(), $this->repository->findAll()),
      static fn (string $id): bool => in_array($id, $ownIds, true),
    ));

    self::assertSame($ownIds, $orderedOwn, 'findAll orders plans by ascending sort order.');
  }

  #[Test]
  public function testFindDefaultReturnsAFlaggedPlan(): void
  {
    $default = Plan::create(
      id: PlanId::fromString('f1000000-0000-4000-8000-000000000061'),
      key: new PlanKey('plan-repo-default'),
      name: 'Default Plan',
      limits: [],
      isDefault: true,
      sortOrder: 0,
    );
    $this->repository->save($default);
    $this->entityManager->clear();

    $found = $this->repository->findDefault();

    self::assertNotNull($found);
    self::assertTrue($found->isDefault());
  }
}
