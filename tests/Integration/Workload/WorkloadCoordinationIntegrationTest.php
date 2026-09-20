<?php

declare(strict_types=1);

namespace Tests\Integration\Workload;

use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Workload\Infrastructure\Adapter\Doctrine\DoctrineWorkloadCoordinationAdapter;

/**
 * Test WorkloadCoordinationIntegrationTest.
 *
 * @category Workload Tests
 */
#[CoversClass(DoctrineWorkloadCoordinationAdapter::class)]
final class WorkloadCoordinationIntegrationTest extends KernelTestCase
{
  #[Test]
  public function testConcurrentPlannersAndCapacityChangesShareTransactionLocks(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $manager */
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    $parameters = $manager->getConnection()->getParams();
    unset($parameters['driverClass']);
    $parameters['driver'] = 'pdo_pgsql';
    $writer = DriverManager::getConnection($parameters);
    $competitor = DriverManager::getConnection($parameters);

    try {
      $writer->beginTransaction();
      $writerManager = $this->createStub(EntityManagerInterface::class);
      $writerManager->method('getConnection')->willReturn($writer);
      $coordination = new DoctrineWorkloadCoordinationAdapter($writerManager);
      $coordination->acquire('lock-test-organization', ['member-b', 'member-a', 'member-a']);
      $competitor->beginTransaction();
      foreach (['member-a', 'member-b'] as $member) {
        self::assertFalse($this->tryLock($competitor, 'workload:member:lock-test-organization:' . $member));
      }
      self::assertFalse($this->tryLock($competitor, 'workload:organization:lock-test-organization'));
      self::assertTrue($this->tryLock($competitor, 'workload:organization:lock-test-organization', true));
      self::assertTrue($this->tryLock($competitor, 'workload:member:lock-test-organization:member-c'));
      self::assertTrue($this->tryLock($competitor, 'workload:member:other-organization:member-a'));
      $writer->commit();
      self::assertTrue($this->tryLock($competitor, 'workload:member:lock-test-organization:member-a'));
      self::assertTrue($this->tryLock($competitor, 'workload:organization:lock-test-organization'));
      $competitor->commit();

      $writer->beginTransaction();
      $coordination->acquire('lock-test-organization', [], true);
      $competitor->beginTransaction();
      self::assertFalse($this->tryLock($competitor, 'workload:organization:lock-test-organization', true));
      $writer->rollBack();
      self::assertTrue($this->tryLock($competitor, 'workload:organization:lock-test-organization', true));
      $competitor->commit();
    } finally {
      if ($writer->isTransactionActive()) {
        $writer->rollBack();
      }
      if ($competitor->isTransactionActive()) {
        $competitor->rollBack();
      }
      $writer->close();
      $competitor->close();
    }
  }

  #[Test]
  public function testCoordinationCannotBeUsedOutsideTheMainTransaction(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $manager */
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    $parameters = $manager->getConnection()->getParams();
    unset($parameters['driverClass']);
    $parameters['driver'] = 'pdo_pgsql';
    $connection = DriverManager::getConnection($parameters);
    $isolatedManager = $this->createStub(EntityManagerInterface::class);
    $isolatedManager->method('getConnection')->willReturn($connection);

    try {
      $this->expectException(LogicException::class);
      new DoctrineWorkloadCoordinationAdapter($isolatedManager)->acquire('lock-test-organization', ['member-a']);
    } finally {
      $connection->close();
    }
  }

  /**
   * Attempts a transaction-scoped lock; acquisition changes database state.
   *
   * @phpstan-impure
   */
  private function tryLock(Connection $connection, string $key, bool $shared = false): bool
  {
    $function = $shared ? 'pg_try_advisory_xact_lock_shared' : 'pg_try_advisory_xact_lock';

    return (bool) $connection->fetchOne('SELECT ' . $function . '(hashtextextended(:key, 0))', ['key' => $key]);
  }
}
