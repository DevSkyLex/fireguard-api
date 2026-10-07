<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Equipment;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\{ArrayParameterType, Connection};
use Maintenance\Application\Contract\Plan\MaintenanceEquipmentOperationsDue;
use Maintenance\Application\Port\Inbound\MaintenanceOperationsDuePort;
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Directory\{MaintenanceEquipmentDirectoryPort, MaintenanceFacilityLifecyclePort};
use Shared\Application\Port\Outbound\ClockPort;

use function array_chunk;
use function array_keys;
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
      $current = [];
      foreach ($this->equipment->findEquipmentByIds($chunk) as $equipment) {
        if ($equipment->organizationId !== $organizationId) {
          continue;
        }
        $facilityKey = $equipment->facilityId ?? '';
        $facilitySuspension[$facilityKey] ??= $this->facilities->isArchived($equipment->facilityId, $organizationId);
        $current[$equipment->equipmentId] = 'decommissioned' !== $equipment->status && !$facilitySuspension[$facilityKey];
        $result[$equipment->equipmentId] = new MaintenanceEquipmentOperationsDue('unscheduled', 'unscheduled', null, null, $mode);
      }
      if ([] === $current) {
        continue;
      }
      $parameters = ['org' => $organizationId, 'ids' => array_keys($current)];
      $types = ['ids' => ArrayParameterType::STRING];
      if ('legacy' === $mode) {
        /** @var list<array{equipment_id:string,due_status:string,next_due_at:?string}> $rows */
        $rows = $this->connection->fetchAllAssociative('SELECT equipment_id, due_status, next_due_at FROM maintenance_schedules WHERE organization_id = :org AND equipment_id IN (:ids)', $parameters, $types);
        foreach ($rows as $row) {
          if ($current[$row['equipment_id']]) {
            $result[$row['equipment_id']] = new MaintenanceEquipmentOperationsDue($row['due_status'], 'unscheduled', $this->date($row['next_due_at']), null, $mode);
          }
        }

        continue;
      }
      /** @var list<array{equipment_id:string,operation_kind:string,next_due_at:?string,equipment_type:string,legacy_schedule_id:?string,interval_override:?string}> $rows */
      $rows = $this->connection->fetchAllAssociative('SELECT p.equipment_id, p.operation_kind, p.next_due_at, p.equipment_type, p.legacy_schedule_id, s.interval_override FROM maintenance_plans p LEFT JOIN maintenance_schedules s ON s.id = p.legacy_schedule_id AND s.organization_id = p.organization_id WHERE p.organization_id = :org AND p.equipment_id IN (:ids) AND p.active = TRUE AND p.archived_at IS NULL', $parameters, $types);
      $groups = [];
      foreach ($rows as $row) {
        if (!$current[$row['equipment_id']] || (null !== $row['legacy_schedule_id'] && null === ($row['interval_override'] ?? $policy->periodicityFor($row['equipment_type'])))) {
          continue;
        }
        $groups[$row['equipment_id']][$row['operation_kind']][] = $this->date($row['next_due_at']);
      }
      foreach ($groups as $equipmentId => $kinds) {
        [$controlStatus, $controlDate] = $this->aggregate($kinds['control'] ?? [], $policy->reminderWindowDays);
        [$serviceStatus, $serviceDate] = $this->aggregate($kinds['maintenance'] ?? [], $policy->reminderWindowDays);
        $result[$equipmentId] = new MaintenanceEquipmentOperationsDue($controlStatus, $serviceStatus, $controlDate, $serviceDate, $mode);
      }
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
      return ['overdue', $first];
    }

    return [$now >= $first->modify('-' . $reminderDays . ' days') ? 'due_soon' : 'up_to_date', $first];
  }

  private function date(?string $date): ?DateTimeImmutable
  {
    return null === $date ? null : new DateTimeImmutable($date, new DateTimeZone('UTC'));
  }
}
