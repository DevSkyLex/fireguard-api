<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\DataFixtures\PlanFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\PlanRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

#[CoversClass(className: PlanFixtures::class)]
final class PlanFixturesIntegrationTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testLoadPersistsThePlanCatalogWithASingleDefaultPlan(): void
  {
    /** @var PlanFixtures $planFixtures */
    $planFixtures = static::getContainer()->get(PlanFixtures::class);

    self::assertSame(['plan', 'main-seed'], PlanFixtures::getGroups());

    $loader = new Loader();
    $loader->addFixture($planFixtures);

    $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
    // Purge before loading: the test databases carry the seeded baseline, so
    // appending on top of it collides on primary keys and makes the counts
    // below meaningless. DAMA rolls the purge back with the rest of the test.
    $executor->execute($loader->getFixtures(), false);

    self::assertSame(3, $this->entityManager->getRepository(PlanRecord::class)->count([]));

    /** @var list<PlanRecord> $plans */
    $plans = $this->entityManager->getRepository(PlanRecord::class)->findBy([], ['sortOrder' => 'ASC']);
    self::assertSame(['free', 'pro', 'max'], array_map(static fn (PlanRecord $plan): string => $plan->key, $plans));

    $free = $this->entityManager->find(PlanRecord::class, PlanFixtures::FREE_PLAN_ID);
    self::assertInstanceOf(PlanRecord::class, $free);
    self::assertTrue($free->isDefault);
    self::assertTrue($free->isActive);
    self::assertSame(['members' => 5, 'facilities' => 2, 'equipment' => 50, 'inspections' => 100], $free->limits);

    $pro = $this->entityManager->find(PlanRecord::class, PlanFixtures::PRO_PLAN_ID);
    self::assertInstanceOf(PlanRecord::class, $pro);
    self::assertFalse($pro->isDefault);

    $max = $this->entityManager->find(PlanRecord::class, PlanFixtures::MAX_PLAN_ID);
    self::assertInstanceOf(PlanRecord::class, $max);
    self::assertSame(3, $max->sortOrder);
  }

  #[Test]
  public function testLoadIsIdempotentAndUpdatesTheExistingCatalogInPlace(): void
  {
    /** @var PlanFixtures $planFixtures */
    $planFixtures = static::getContainer()->get(PlanFixtures::class);

    $loader = new Loader();
    $loader->addFixture($planFixtures);

    $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
    $executor->execute($loader->getFixtures(), false);

    // Re-running the fixture must reuse the existing rows rather than raise a
    // duplicate-key violation: seed runs outside a test have no DAMA rollback.
    $planFixtures->load($this->entityManager);

    self::assertSame(3, $this->entityManager->getRepository(PlanRecord::class)->count([]));
  }
}
