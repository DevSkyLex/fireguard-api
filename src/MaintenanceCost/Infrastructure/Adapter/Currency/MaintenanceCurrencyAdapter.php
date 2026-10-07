<?php

declare(strict_types=1);

namespace MaintenanceCost\Infrastructure\Adapter\Currency;

use Doctrine\DBAL\Connection;
use LogicException;
use MaintenanceCost\Application\Contract\Currency\MaintenanceCurrencySnapshot;
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use MaintenanceCost\Application\Port\Outbound\Currency\MaintenanceCurrencyStorePort;

/** Configuration and first financial fact serialize on the same main transaction lock. */
final readonly class MaintenanceCurrencyAdapter implements MaintenanceCurrencyPort, MaintenanceCurrencyStorePort
{
  public function __construct(private Connection $connection)
  {
  }

  public function forOrganization(string $organizationId): string
  {
    return $this->read($organizationId)->currency;
  }

  public function read(string $organizationId): MaintenanceCurrencySnapshot
  {
    /** @var array{currency:string,locked:bool}|false $row */
    $row = $this->connection->fetchAssociative('SELECT currency, locked FROM maintenance_cost_currency_settings WHERE organization_id = :org', ['org' => $organizationId]);

    return new MaintenanceCurrencySnapshot($organizationId, false === $row ? 'EUR' : $row['currency'], false !== $row && $row['locked']);
  }

  public function synchronized(string $organizationId, callable $work): mixed
  {
    return $this->connection->transactional(function () use ($organizationId, $work): mixed {
      $this->scopeLock($organizationId);

      return $work();
    });
  }

  public function lock(string $organizationId): string
  {
    $this->requireTransaction();
    $this->scopeLock($organizationId);
    $this->connection->executeStatement("INSERT INTO maintenance_cost_currency_settings (organization_id, currency, locked) VALUES (:org, 'EUR', TRUE) ON CONFLICT (organization_id) DO UPDATE SET locked = TRUE", ['org' => $organizationId]);

    return $this->forOrganization($organizationId);
  }

  public function save(MaintenanceCurrencySnapshot $currency): void
  {
    $this->requireTransaction();
    $this->connection->executeStatement('INSERT INTO maintenance_cost_currency_settings (organization_id, currency, locked) VALUES (:org, :currency, :locked) ON CONFLICT (organization_id) DO UPDATE SET currency = EXCLUDED.currency, locked = EXCLUDED.locked', [
      'org' => $currency->organizationId, 'currency' => $currency->currency, 'locked' => $currency->locked,
    ], ['locked' => \Doctrine\DBAL\ParameterType::BOOLEAN]);
  }

  private function scopeLock(string $organizationId): void
  {
    $this->connection->executeStatement('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'maintenance_cost.currency.' . $organizationId]);
  }

  private function requireTransaction(): void
  {
    if (!$this->connection->isTransactionActive()) {
      throw new LogicException('Currency writes require the active main transaction.');
    }
  }
}
