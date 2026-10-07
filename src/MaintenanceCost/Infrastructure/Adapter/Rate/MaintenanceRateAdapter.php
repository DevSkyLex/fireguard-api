<?php

declare(strict_types=1);

namespace MaintenanceCost\Infrastructure\Adapter\Rate;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use LogicException;
use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;
use MaintenanceCost\Application\Port\Inbound\MaintenanceRatePort;
use MaintenanceCost\Application\Port\Outbound\Rate\MaintenanceRateStorePort;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;

use function array_map;
use function max;
use function min;

/** Immutable rates resolve the current common currency; financial owners capture both in their facts. */
final readonly class MaintenanceRateAdapter implements MaintenanceRatePort, MaintenanceRateStorePort
{
  private const string SELECT = "SELECT rates.id, rates.member_id, rates.hourly_amount, rates.effective_from, COALESCE(settings.currency, 'EUR') AS currency FROM maintenance_cost_hourly_rates rates LEFT JOIN maintenance_cost_currency_settings settings ON settings.organization_id = rates.organization_id ";

  public function __construct(private Connection $connection)
  {
  }

  public function synchronized(string $organizationId, callable $work): mixed
  {
    return $this->connection->transactional(function () use ($organizationId, $work): mixed {
      $this->connection->executeStatement('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'maintenance_cost.currency.' . $organizationId]);

      return $work();
    });
  }

  public function forMember(string $organizationId, string $memberId, string $workedOn): ?MaintenanceRateSnapshot
  {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $workedOn);
    if (false === $date || $date->format('Y-m-d') !== $workedOn) {
      throw MaintenanceCostException::invalid('Worked-on date must be a valid YYYY-MM-DD date.');
    }

    return $this->find(self::SELECT . 'WHERE rates.organization_id = :org AND rates.member_id = :member AND rates.effective_from <= :worked ORDER BY rates.effective_from DESC, rates.id ASC LIMIT 1', ['org' => $organizationId, 'member' => $memberId, 'worked' => $workedOn]);
  }

  public function findByClientId(string $organizationId, string $clientId): ?MaintenanceRateSnapshot
  {
    return $this->find(self::SELECT . 'WHERE rates.organization_id = :org AND rates.client_id = :client', ['org' => $organizationId, 'client' => $clientId]);
  }

  public function findByEffectiveDate(string $organizationId, string $memberId, string $effectiveFrom): ?MaintenanceRateSnapshot
  {
    return $this->find(self::SELECT . 'WHERE rates.organization_id = :org AND rates.member_id = :member AND rates.effective_from = :date', ['org' => $organizationId, 'member' => $memberId, 'date' => $effectiveFrom]);
  }

  public function append(string $organizationId, string $clientId, MaintenanceRateSnapshot $rate): void
  {
    if (!$this->connection->isTransactionActive()) {
      throw new LogicException('Appending a rate requires the active main transaction.');
    }
    $this->connection->insert('maintenance_cost_hourly_rates', [
      'id' => $rate->id, 'organization_id' => $organizationId, 'member_id' => $rate->memberId,
      'client_id' => $clientId, 'hourly_amount' => $rate->hourlyAmount, 'effective_from' => $rate->effectiveFrom,
    ]);
  }

  public function list(string $organizationId, int $limit, int $offset, ?string $memberId = null): array
  {
    $parameters = ['org' => $organizationId];
    $where = 'rates.organization_id = :org';
    if (null !== $memberId) {
      $where .= ' AND rates.member_id = :member';
      $parameters['member'] = $memberId;
    }
    /** @var list<array<string, mixed>> $rows */
    $rows = $this->connection->fetchAllAssociative(self::SELECT . 'WHERE ' . $where . ' ORDER BY rates.effective_from DESC, rates.id ASC LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset), $parameters);

    return array_map($this->snapshot(...), $rows);
  }

  public function count(string $organizationId, ?string $memberId = null): int
  {
    $parameters = ['org' => $organizationId];
    $where = 'organization_id = :org';
    if (null !== $memberId) {
      $where .= ' AND member_id = :member';
      $parameters['member'] = $memberId;
    }
    /** @var int|numeric-string $count */
    $count = $this->connection->fetchOne('SELECT COUNT(*) FROM maintenance_cost_hourly_rates WHERE ' . $where, $parameters);

    return (int) $count;
  }

  /**
   * @param array<string,string> $parameters
   */
  private function find(string $sql, array $parameters): ?MaintenanceRateSnapshot
  {
    /** @var array<string,mixed>|false $row */
    $row = $this->connection->fetchAssociative($sql, $parameters);

    return false === $row ? null : $this->snapshot($row);
  }

  /**
   * @param array<string,mixed> $row
   */
  private function snapshot(array $row): MaintenanceRateSnapshot
  {
    /** @var array{id:string,member_id:string,hourly_amount:string,currency:string,effective_from:string} $row */
    return new MaintenanceRateSnapshot($row['id'], $row['member_id'], $row['hourly_amount'], $row['currency'], $row['effective_from']);
  }
}
