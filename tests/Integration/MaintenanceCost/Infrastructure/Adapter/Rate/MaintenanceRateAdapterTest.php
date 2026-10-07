<?php

declare(strict_types=1);

namespace Tests\Integration\MaintenanceCost\Infrastructure\Adapter\Rate;

use Doctrine\DBAL\Connection;
use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;
use MaintenanceCost\Infrastructure\Adapter\Rate\MaintenanceRateAdapter;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Effective-rate lookup remains scoped and exact on the production database engine. */
final class MaintenanceRateAdapterTest extends KernelTestCase
{
  private const string ORG = '650e8400-e29b-41d4-a716-448040000001';

  private const string MEMBER = '650e8400-e29b-41d4-a716-448040000004';

  #[Test]
  public function selectsTheLastEffectiveRateAndKeepsAbsenceExplicit(): void
  {
    $adapter = $this->adapter();
    $first = new MaintenanceRateSnapshot('650e8400-e29b-41d4-a716-448040000011', self::MEMBER, '42.000001', 'EUR', '2026-01-01');
    $second = new MaintenanceRateSnapshot('650e8400-e29b-41d4-a716-448040000012', self::MEMBER, '43.000001', 'EUR', '2026-07-01');
    $adapter->synchronized(self::ORG, static function () use ($adapter, $first, $second): void {
      $adapter->append(self::ORG, '650e8400-e29b-41d4-a716-448040000021', $first);
      $adapter->append(self::ORG, '650e8400-e29b-41d4-a716-448040000022', $second);
    });
    self::assertNull($adapter->forMember(self::ORG, self::MEMBER, '2025-12-31'));
    self::assertSame('42.000001', $adapter->forMember(self::ORG, self::MEMBER, '2026-06-30')?->hourlyAmount);
    self::assertSame('43.000001', $adapter->forMember(self::ORG, self::MEMBER, '2026-07-01')?->hourlyAmount);
    self::assertNull($adapter->forMember('650e8400-e29b-41d4-a716-448040000099', self::MEMBER, '2026-07-01'));
    self::assertSame(2, $adapter->count(self::ORG));
    self::assertCount(1, $adapter->list(self::ORG, 1, 1));
  }

  #[Test]
  public function snapshotsUseTheOrganizationsCommonConfiguredCurrency(): void
  {
    $adapter = $this->adapter();
    /** @var Connection $db */
    $db = self::getContainer()->get('doctrine.dbal.main_connection');
    $db->insert('maintenance_cost_currency_settings', ['organization_id' => self::ORG, 'currency' => 'USD', 'locked' => false], ['locked' => \Doctrine\DBAL\ParameterType::BOOLEAN]);
    $rate = new MaintenanceRateSnapshot('650e8400-e29b-41d4-a716-448040000011', self::MEMBER, '42.000001', 'USD', '2026-01-01');
    $adapter->synchronized(self::ORG, static fn () => $adapter->append(self::ORG, '650e8400-e29b-41d4-a716-448040000021', $rate));
    self::assertSame('USD', $adapter->forMember(self::ORG, self::MEMBER, '2026-01-01')?->currency);
  }

  private function adapter(): MaintenanceRateAdapter
  {
    self::bootKernel();
    /** @var Connection $connection */
    $connection = self::getContainer()->get('doctrine.dbal.main_connection');

    return new MaintenanceRateAdapter($connection);
  }
}
