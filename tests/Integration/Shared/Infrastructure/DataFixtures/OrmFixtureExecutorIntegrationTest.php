<?php

declare(strict_types=1);

namespace Tests\Integration\Shared\Infrastructure\DataFixtures;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Infrastructure\DataFixtures\PlanFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\PlanRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Infrastructure\DataFixtures\{OrmFixtureExecutor, SeedFixtureAdapter, SeedReferenceRegistryAdapter};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(className: OrmFixtureExecutor::class)]
final class OrmFixtureExecutorIntegrationTest extends KernelTestCase
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
  public function testExecutePurgesThenLoadsTheGivenFixturesAndClearsTheUnitOfWork(): void
  {
    /** @var PlanFixtures $planFixtures */
    $planFixtures = static::getContainer()->get(PlanFixtures::class);

    // Purging is the point of the non-append mode: the seeded baseline goes
    // away and only the fixture's own rows remain. DAMA rolls this back.
    new OrmFixtureExecutor()->execute($this->entityManager, [$planFixtures], false);

    // The executor clears the unit of work in a `finally`, so no fixture
    // entity is left managed once the run returns.
    self::assertSame(0, $this->entityManager->getUnitOfWork()->size());
    self::assertSame(3, $this->entityManager->getRepository(PlanRecord::class)->count([]));
    self::assertSame(0, $this->organizationCount());
  }

  #[Test]
  public function testExecuteInAppendModeKeepsExistingRows(): void
  {
    /** @var PlanFixtures $planFixtures */
    $planFixtures = static::getContainer()->get(PlanFixtures::class);

    $organizationsBefore = $this->organizationCount();

    new OrmFixtureExecutor()->execute($this->entityManager, [$planFixtures], true);

    // Append mode must not purge: unrelated seeded tables are left untouched.
    self::assertSame($organizationsBefore, $this->organizationCount());
    self::assertSame(3, $this->entityManager->getRepository(PlanRecord::class)->count([]));
  }

  #[Test]
  public function testNamedCapabilitiesRunTheirPrerequisitesFirstRegardlessOfRegistrationOrder(): void
  {
    $order = [];
    $first = $this->createMock(FixtureInterface::class);
    $first->expects(self::once())->method('load')->willReturnCallback(static function () use (&$order): void {
      $order[] = 'organization';
    });
    $second = $this->createMock(FixtureInterface::class);
    $second->expects(self::once())->method('load')->willReturnCallback(static function () use (&$order): void {
      $order[] = 'equipment';
    });
    $references = new SeedReferenceRegistryAdapter();
    $fixtures = [
      new SeedFixtureAdapter($second, $this->entityManager, $references, 'equipment', ['organization']),
      new SeedFixtureAdapter($first, $this->entityManager, $references, 'organization'),
    ];
    $organizationsBefore = $this->organizationCount();

    new OrmFixtureExecutor($references)->execute($this->entityManager, $fixtures, true);

    self::assertSame(['organization', 'equipment'], $order);
    self::assertSame($organizationsBefore, $this->organizationCount());
    self::assertSame(0, $this->entityManager->getUnitOfWork()->size());
  }

  private function organizationCount(): int
  {
    $count = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM organizations');
    self::assertIsNumeric($count);

    return (int) $count;
  }
}
