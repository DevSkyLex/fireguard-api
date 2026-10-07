<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Outbound\Plan;

use DateTimeImmutable;
use Maintenance\Application\Contract\Plan\{MaintenanceOccurrenceState, MaintenanceOperationResult, MaintenancePlanState};

/** Main-database persistence and engine authority; all writes serialize by organization. */
interface MaintenancePlanStorePort
{
  /**
   * @template T
   *
   * @param callable():T $work
   *
   * @return T
   */
  public function synchronized(string $organizationId, callable $work): mixed;

  public function engineMode(string $organizationId): string;

  public function activateEngine(string $organizationId, DateTimeImmutable $now): void;

  public function find(string $organizationId, string $id): ?MaintenancePlanState;

  public function findByLegacySchedule(string $organizationId, string $scheduleId): ?MaintenancePlanState;

  /**
   * @return list<MaintenancePlanState>
   */
  public function list(string $organizationId, int $limit, int $offset, ?string $equipmentId = null, ?string $operationKind = null, ?string $search = null, bool $includeArchived = false): array;

  public function count(string $organizationId, ?string $equipmentId = null, ?string $operationKind = null, ?string $search = null): int;

  public function save(MaintenancePlanState $plan): void;

  public function openOccurrence(string $organizationId, string $planId): ?MaintenanceOccurrenceState;

  public function findOccurrence(string $organizationId, string $id): ?MaintenanceOccurrenceState;

  public function saveOccurrence(MaintenanceOccurrenceState $occurrence): void;

  public function hasReceipt(string $resultId, string $occurrenceId): bool;

  public function saveReceipt(MaintenanceOperationResult $result): void;
}
