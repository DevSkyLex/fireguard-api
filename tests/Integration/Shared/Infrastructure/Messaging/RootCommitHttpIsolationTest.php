<?php

declare(strict_types=1);

namespace Tests\Integration\Shared\Infrastructure\Messaging;

use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\{Connection, DriverManager};
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Application\Port\Outbound\RootTransactionManagerPort;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;

use function hash;

/**
 * Class RootCommitHttpIsolationTest
 *
 * Proves real kernel commits and pristine per-test state using the same isolation trait as selected HTTP suites.
 *
 * @category Integration Test
 */
#[SkipDatabaseRollback]
final class RootCommitHttpIsolationTest extends KernelTestCase
{
  use RootCommitDatabaseIsolationTrait;

  #[DataProvider('attempts')]
  public function testEachKernelCanCommitWithoutLeakingStateToTheNextTest(int $attempt): void
  {
    self::assertFalse(StaticDriver::isKeepStaticConnections());
    $container = self::getContainer();
    $connection = $container->get('doctrine.dbal.main_connection');
    self::assertInstanceOf(Connection::class, $connection);
    self::assertFalse($connection->isTransactionActive());
    $id = hash('sha256', 'root-commit-http-isolation-regression');
    self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM consumed_events WHERE id = ?', [$id]));
    $transactions = $container->get(RootTransactionManagerPort::class);
    self::assertInstanceOf(RootTransactionManagerPort::class, $transactions);
    $transactions->transactional(static fn () => $connection->executeStatement('INSERT INTO consumed_events (id, consumed_at) VALUES (?, CURRENT_TIMESTAMP)', [$id]));
    $url = $_ENV['MAIN_DATABASE_URL'];
    self::assertIsString($url);
    $observer = DriverManager::getConnection(['url' => $url]);

    try {
      self::assertSame(1, $observer->fetchOne('SELECT COUNT(*) FROM consumed_events WHERE id = ?', [$id]));
      self::assertGreaterThan(0, $attempt);
    } finally {
      $observer->close();
    }
  }

  /**
   * @return iterable<string, array{int}>
   */
  public static function attempts(): iterable
  {
    yield 'first real commit' => [1];
    yield 'second pristine database' => [2];
  }
}
