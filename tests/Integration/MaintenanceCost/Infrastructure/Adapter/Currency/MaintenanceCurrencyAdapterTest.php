<?php

declare(strict_types=1);

namespace Tests\Integration\MaintenanceCost\Infrastructure\Adapter\Currency;

use Doctrine\DBAL\Connection;
use MaintenanceCost\Application\Contract\Currency\MaintenanceCurrencySnapshot;
use MaintenanceCost\Infrastructure\Adapter\Currency\MaintenanceCurrencyAdapter;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Real PostgreSQL transactions retain one currency across the first captured financial fact. */
final class MaintenanceCurrencyAdapterTest extends KernelTestCase
{
  private const string ORG = '650e8400-e29b-41d4-a716-448040000001';

  #[Test]
  public function readsTheDefaultWithoutCreatingASetting(): void
  {
    $db = $this->connection();
    $adapter = new MaintenanceCurrencyAdapter($db);
    self::assertSame('EUR', $adapter->forOrganization(self::ORG));
    self::assertFalse($adapter->read(self::ORG)->locked);
    self::assertSame(0, $this->countSettings($db));
  }

  #[Test]
  public function firstFactFreezesTheConfiguredCurrencyAndReplayKeepsIt(): void
  {
    $db = $this->connection();
    $adapter = new MaintenanceCurrencyAdapter($db);
    $adapter->synchronized(self::ORG, static fn () => $adapter->save(new MaintenanceCurrencySnapshot(self::ORG, 'USD', false)));
    $db->transactional(static fn () => self::assertSame('USD', $adapter->lock(self::ORG)));
    $db->transactional(static fn () => self::assertSame('USD', $adapter->lock(self::ORG)));
    self::assertTrue($adapter->read(self::ORG)->locked);
    self::assertSame(1, $this->countSettings($db));
  }

  #[Test]
  public function rollbackOfTheFirstFactDoesNotLeaveItsCurrencyLock(): void
  {
    $db = $this->connection();
    $adapter = new MaintenanceCurrencyAdapter($db);
    $db->beginTransaction();
    $adapter->lock(self::ORG);
    $db->rollBack();
    self::assertFalse($adapter->read(self::ORG)->locked);
    self::assertSame(0, $this->countSettings($db));
  }

  private function connection(): Connection
  {
    self::bootKernel();
    /** @var Connection $connection */
    $connection = self::getContainer()->get('doctrine.dbal.main_connection');

    return $connection;
  }

  private function countSettings(Connection $db): int
  {
    /** @var int|numeric-string $count */
    $count = $db->fetchOne('SELECT COUNT(*) FROM maintenance_cost_currency_settings WHERE organization_id = :org', ['org' => self::ORG]);

    return (int) $count;
  }
}
