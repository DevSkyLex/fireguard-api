<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Maintenance\Application\Contract\Plan\{MaintenanceOccurrenceState, MaintenanceOperationResult, MaintenancePlanState};
use Maintenance\Application\Port\Outbound\Plan\MaintenancePlanStorePort;
use Maintenance\Infrastructure\Persistence\Doctrine\Mapper\MaintenancePlanStateMapper;

use function array_keys;
use function array_map;
use function implode;
use function is_string;
use function strtolower;

/** DBAL writes share the main connection with drafts and the transactional outbox. */
final readonly class MaintenancePlanRepository implements MaintenancePlanStorePort
{
  // #region Properties
  /**
   * Property mapper
   *
   * Preserves historical storage and calendar conversions independently of database operations.
   */
  private MaintenancePlanStateMapper $mapper;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param Connection $connection explicitly configured main connection
   *
   * @return void
   */
  public function __construct(private Connection $connection)
  {
    $this->mapper = new MaintenancePlanStateMapper();
  }
  // #endregion

  // #region Methods
  public function synchronized(string $organizationId, callable $work): mixed
  {
    return $this->connection->transactional(function () use ($organizationId, $work): mixed {
      $this->connection->executeStatement('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'maintenance.engine.' . $organizationId]);

      return $work();
    });
  }

  public function engineMode(string $organizationId): string
  {
    $mode = $this->connection->fetchOne('SELECT mode FROM maintenance_engines WHERE organization_id = :org', ['org' => $organizationId]);

    return is_string($mode) ? $mode : 'legacy';
  }

  public function activateEngine(string $organizationId, DateTimeImmutable $now): void
  {
    $this->connection->executeStatement("INSERT INTO maintenance_engines (organization_id, mode, activated_at) VALUES (:org, 'plans', :now) ON CONFLICT (organization_id) DO NOTHING", ['org' => $organizationId, 'now' => $this->mapper->date($now)]);
  }

  public function find(string $organizationId, string $id): ?MaintenancePlanState
  {
    /** @var array<string, bool|int|string|null>|false $row */
    $row = $this->connection->fetchAssociative('SELECT * FROM maintenance_plans WHERE id = :id AND organization_id = :org', ['id' => $id, 'org' => $organizationId]);

    return false === $row ? null : $this->mapper->plan($row);
  }

  public function findByLegacySchedule(string $organizationId, string $scheduleId): ?MaintenancePlanState
  {
    /** @var array<string, bool|int|string|null>|false $row */
    $row = $this->connection->fetchAssociative('SELECT * FROM maintenance_plans WHERE legacy_schedule_id = :id AND organization_id = :org', ['id' => $scheduleId, 'org' => $organizationId]);

    return false === $row ? null : $this->mapper->plan($row);
  }

  public function list(string $organizationId, int $limit, int $offset, ?string $equipmentId = null, ?string $operationKind = null, ?string $search = null, bool $includeArchived = false): array
  {
    [$where, $parameters] = $this->criteria($organizationId, $equipmentId, $operationKind, $search, $includeArchived);
    /** @var list<array<string, bool|int|string|null>> $rows */
    $rows = $this->connection->fetchAllAssociative('SELECT * FROM maintenance_plans WHERE ' . $where . ' ORDER BY next_due_at ASC NULLS LAST, id ASC LIMIT ' . $limit . ' OFFSET ' . $offset, $parameters);

    return array_map($this->mapper->plan(...), $rows);
  }

  public function count(string $organizationId, ?string $equipmentId = null, ?string $operationKind = null, ?string $search = null): int
  {
    [$where, $parameters] = $this->criteria($organizationId, $equipmentId, $operationKind, $search);

    /** @var int|numeric-string $count */
    $count = $this->connection->fetchOne('SELECT COUNT(*) FROM maintenance_plans WHERE ' . $where, $parameters);

    return (int) $count;
  }

  public function save(MaintenancePlanState $plan): void
  {
    $this->upsert('maintenance_plans', $this->mapper->snapshot($plan));
  }

  public function openOccurrence(string $organizationId, string $planId): ?MaintenanceOccurrenceState
  {
    /** @var array<string, bool|int|string|null>|false $row */
    $row = $this->connection->fetchAssociative("SELECT * FROM maintenance_occurrences WHERE organization_id = :org AND plan_id = :plan AND status = 'open'", ['org' => $organizationId, 'plan' => $planId]);

    return false === $row ? null : $this->mapper->occurrence($row);
  }

  public function findOccurrence(string $organizationId, string $id): ?MaintenanceOccurrenceState
  {
    /** @var array<string, bool|int|string|null>|false $row */
    $row = $this->connection->fetchAssociative('SELECT * FROM maintenance_occurrences WHERE organization_id = :org AND id = :id', ['org' => $organizationId, 'id' => $id]);

    return false === $row ? null : $this->mapper->occurrence($row);
  }

  public function saveOccurrence(MaintenanceOccurrenceState $occurrence): void
  {
    $this->upsert('maintenance_occurrences', $this->mapper->snapshot($occurrence));
  }

  public function hasReceipt(string $resultId, string $occurrenceId): bool
  {
    return false !== $this->connection->fetchOne('SELECT id FROM maintenance_operation_receipts WHERE id = :id AND occurrence_id = :occurrence', ['id' => $resultId, 'occurrence' => $occurrenceId]);
  }

  public function saveReceipt(MaintenanceOperationResult $result): void
  {
    $this->connection->insert('maintenance_operation_receipts', ['id' => $result->resultId, 'occurrence_id' => $result->occurrenceId, 'organization_id' => $result->organizationId, 'outcome' => $result->outcome, 'performed_at' => $this->mapper->date($result->performedAt)]);
  }

  /**
   * @return array{string, array<string, string>}
   */
  private function criteria(string $organizationId, ?string $equipmentId, ?string $operationKind, ?string $search, bool $includeArchived = false): array
  {
    $where = 'organization_id = :org' . ($includeArchived ? '' : ' AND archived_at IS NULL');
    $parameters = ['org' => $organizationId];
    foreach (['equipment_id' => $equipmentId, 'operation_kind' => $operationKind] as $column => $value) {
      if (null !== $value) {
        $where .= ' AND ' . $column . ' = :' . $column;
        $parameters[$column] = $value;
      }
    }
    if (null !== $search && '' !== $search) {
      $where .= ' AND LOWER(name) LIKE :search';
      $parameters['search'] = '%' . strtolower($search) . '%';
    }

    return [$where, $parameters];
  }

  /**
   * @param array<string, mixed> $data
   */
  private function upsert(string $table, array $data): void
  {
    $columns = array_keys($data);
    $updates = array_map(static fn (string $column): string => $column . ' = EXCLUDED.' . $column, $columns);
    $types = [];
    foreach ($data as $column => $value) {
      if ('active' === $column) {
        $types[$column] = \Doctrine\DBAL\ParameterType::BOOLEAN;
      }
    }
    $this->connection->executeStatement('INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns)) . ') ON CONFLICT (id) DO UPDATE SET ' . implode(', ', $updates), $data, $types);
  }

  // #endregion
}
