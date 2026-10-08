<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Equipment;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\{ArrayParameterType, Connection};
use Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy;
use Maintenance\Application\Contract\Plan\MaintenanceEquipmentOperationsDue;
use Maintenance\Application\Port\Inbound\MaintenanceOperationsDuePort;
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Directory\{MaintenanceEquipmentDirectoryPort, MaintenanceFacilityLifecyclePort};
use Shared\Application\Port\Outbound\ClockPort;

use function array_chunk;
use function array_keys;
use function array_replace;
use function array_unique;
use function is_string;

/** Reads each operation kind independently without mutating engine or schedule state. */
final readonly class MaintenanceOperationsDueAdapter implements MaintenanceOperationsDuePort
{
  public function __construct(
    private Connection $connection,
    private MaintenanceEquipmentDirectoryPort $equipment,
    private MaintenanceFacilityLifecyclePort $facilities,
    private MaintenanceCompliancePolicyPort $compliance,
    private ClockPort $clock,
  ) {
  }

  public function forEquipment(string $organizationId, array $equipmentIds): array
  {
    if ([] === $equipmentIds) {
      return [];
    }
    $modeValue = $this->connection->fetchOne('SELECT mode FROM maintenance_engines WHERE organization_id = :org', ['org' => $organizationId]);
    $mode = is_string($modeValue) ? $modeValue : 'legacy';
    $policy = $this->compliance->compliancePolicy($organizationId);
    $result = [];
    $facilitySuspension = [];
    foreach (array_chunk(array_unique($equipmentIds), 200) as $chunk) {
      $current = $this->currentEquipment($organizationId, $chunk, $facilitySuspension);
      if ([] === $current) {
        continue;
      }
      foreach (array_keys($current) as $equipmentId) {
        $result[$equipmentId] = new MaintenanceEquipmentOperationsDue('unscheduled', 'unscheduled', null, null, $mode);
      }
      $due = 'legacy' === $mode ? $this->legacyDue($organizationId, $current, $mode) : $this->planDue($organizationId, $current, $policy, $mode);
      $result = array_replace($result, $due);
    }

    return $result;
  }

  /**
   * Method currentEquipment
   *
   * Resolves lifecycle and organization scope before reading either due-state engine.
   *
   * @access private
   *
   * @param string $organizationId the organization to project
   * @param list<string> $equipmentIds the bounded equipment chunk
   * @param array<string, bool> $facilitySuspension the shared facility lifecycle cache
   *
   * @return array<string, bool> scoped equipment and whether its lifecycle is trackable
   */
  private function currentEquipment(string $organizationId, array $equipmentIds, array &$facilitySuspension): array
  {
    $current = [];
    foreach ($this->equipment->findEquipmentByIds($equipmentIds) as $equipment) {
      if ($equipment->organizationId !== $organizationId) {
        continue;
      }
      $facilityKey = $equipment->facilityId ?? '';
      $facilitySuspension[$facilityKey] ??= $this->facilities->isArchived($equipment->facilityId, $organizationId);
      $current[$equipment->equipmentId] = 'decommissioned' !== $equipment->status && !$facilitySuspension[$facilityKey];
    }

    return $current;
  }

  /**
   * Method legacyDue
   *
   * Reads historical controls while suspended equipment keeps its unscheduled default.
   *
   * @access private
   *
   * @param string $organizationId the scoped organization
   * @param array<string, bool> $current the equipment lifecycle decisions
   * @param string $mode the persisted engine authority
   *
   * @return array<string, MaintenanceEquipmentOperationsDue> historical control statuses
   */
  private function legacyDue(string $organizationId, array $current, string $mode): array
  {
    /** @var list<array{equipment_id:string,due_status:string,next_due_at:?string}> $rows */
    $rows = $this->connection->fetchAllAssociative('SELECT equipment_id, due_status, next_due_at FROM maintenance_schedules WHERE organization_id = :org AND equipment_id IN (:ids)', ['org' => $organizationId, 'ids' => array_keys($current)], ['ids' => ArrayParameterType::STRING]);
    $result = [];
    foreach ($rows as $row) {
      if ($current[$row['equipment_id']]) {
        $result[$row['equipment_id']] = new MaintenanceEquipmentOperationsDue($row['due_status'], 'unscheduled', $this->date($row['next_due_at']), null, $mode);
      }
    }

    return $result;
  }

  /**
   * Method planDue
   *
   * Aggregates controls and servicing independently, retaining historical cadence availability.
   *
   * @access private
   *
   * @param string $organizationId the scoped organization
   * @param array<string, bool> $current the equipment lifecycle decisions
   * @param MaintenanceCompliancePolicy $policy the current historical defaults and reminder window
   * @param string $mode the persisted engine authority
   *
   * @return array<string, MaintenanceEquipmentOperationsDue> independent operation statuses
   */
  private function planDue(string $organizationId, array $current, MaintenanceCompliancePolicy $policy, string $mode): array
  {
    /** @var list<array{equipment_id:string,operation_kind:string,next_due_at:?string,equipment_type:string,legacy_schedule_id:?string,interval_override:?string}> $rows */
    $rows = $this->connection->fetchAllAssociative('SELECT p.equipment_id, p.operation_kind, p.next_due_at, p.equipment_type, p.legacy_schedule_id, s.interval_override FROM maintenance_plans p LEFT JOIN maintenance_schedules s ON s.id = p.legacy_schedule_id AND s.organization_id = p.organization_id WHERE p.organization_id = :org AND p.equipment_id IN (:ids) AND p.active = TRUE AND p.archived_at IS NULL', ['org' => $organizationId, 'ids' => array_keys($current)], ['ids' => ArrayParameterType::STRING]);
    $groups = [];
    foreach ($rows as $row) {
      if (!$current[$row['equipment_id']] || (null !== $row['legacy_schedule_id'] && null === ($row['interval_override'] ?? $policy->periodicityFor($row['equipment_type'])))) {
        continue;
      }
      $groups[$row['equipment_id']][$row['operation_kind']][] = $this->date($row['next_due_at']);
    }
    $result = [];
    foreach ($groups as $equipmentId => $kinds) {
      [$controlStatus, $controlDate] = $this->aggregate($kinds['control'] ?? [], $policy->reminderWindowDays);
      [$serviceStatus, $serviceDate] = $this->aggregate($kinds['maintenance'] ?? [], $policy->reminderWindowDays);
      $result[$equipmentId] = new MaintenanceEquipmentOperationsDue($controlStatus, $serviceStatus, $controlDate, $serviceDate, $mode);
    }

    return $result;
  }

  /**
   * @param list<?DateTimeImmutable> $dates
   *
   * @return array{string, ?DateTimeImmutable}
   */
  private function aggregate(array $dates, int $reminderDays): array
  {
    if ([] === $dates) {
      return ['unscheduled', null];
    }
    $first = null;
    foreach ($dates as $date) {
      if (null === $date) {
        return ['overdue', null];
      }
      if (null === $first || $date < $first) {
        $first = $date;
      }
    }
    $now = $this->clock->now();
    if ($now > $first) {
      $status = 'overdue';
    } else {
      $status = $now >= $first->modify('-' . $reminderDays . ' days') ? 'due_soon' : 'up_to_date';
    }

    return [$status, $first];
  }

  private function date(?string $date): ?DateTimeImmutable
  {
    return null === $date ? null : new DateTimeImmutable($date, new DateTimeZone('UTC'));
  }
}
