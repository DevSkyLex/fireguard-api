<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use DateTimeZone;
use Maintenance\Application\Contract\Plan\{MaintenanceOccurrenceState, MaintenancePlanState};

use function get_object_vars;
use function preg_replace;
use function strtolower;

/**
 * Class MaintenancePlanStateMapper
 *
 * Owns exact persisted plan and occurrence restoration without applying current creation policy.
 * Calendar slots retain the plan's frozen timezone; historical execution instants remain UTC.
 *
 * @category Mapper
 */
final readonly class MaintenancePlanStateMapper
{
  // #region Methods
  /**
   * Method snapshot
   *
   * Preserves every public persisted field and writes dates in the historical UTC storage format.
   *
   * @access public
   *
   * @param MaintenancePlanState|MaintenanceOccurrenceState $state accepted persisted snapshot
   *
   * @return array<string, mixed> column values without database side effects
   */
  public function snapshot(MaintenancePlanState|MaintenanceOccurrenceState $state): array
  {
    $data = [];
    foreach (get_object_vars($state) as $property => $value) {
      $column = strtolower((string) preg_replace('/[A-Z]/', '_$0', $property));
      $data[$column] = $value instanceof DateTimeImmutable ? $this->date($value) : $value;
    }

    return $data;
  }

  /**
   * Method date
   *
   * @access public
   *
   * @param DateTimeImmutable $date source instant without mutation
   *
   * @return string historical UTC storage representation at second precision
   */
  public function date(DateTimeImmutable $date): string
  {
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  }

  /**
   * Method plan
   *
   * Restores raw historical values and the frozen calendar independently of current plan validation.
   *
   * @access public
   *
   * @param array<string, bool|int|string|null> $row organization-scoped persisted plan
   *
   * @return MaintenancePlanState exact persisted state with calendar slots in their original timezone
   */
  public function plan(array $row): MaintenancePlanState
  {
    return new MaintenancePlanState((string) $row['id'], (string) $row['organization_id'], (string) $row['equipment_id'], null === $row['facility_id'] ? null : (string) $row['facility_id'], (string) $row['equipment_type'], (string) $row['name'], (string) $row['operation_kind'], (string) $row['interval'], (string) $row['cadence_mode'], $this->parsed($row['anchor_at'])?->setTimezone(new DateTimeZone((string) $row['calendar_timezone'])), $this->parsed($row['next_due_at'])?->setTimezone(new DateTimeZone((string) $row['calendar_timezone'])), (bool) $row['active'], null === $row['legacy_schedule_id'] ? null : (string) $row['legacy_schedule_id'], $this->parsed($row['last_completed_at']), $this->parsed($row['archived_at']), new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')), new DateTimeImmutable((string) $row['updated_at'], new DateTimeZone('UTC')), (string) $row['calendar_timezone']);
  }

  /**
   * Method occurrence
   *
   * Preserves the original due instant, retry lineage and historical result links.
   *
   * @access public
   *
   * @param array<string, bool|int|string|null> $row organization-scoped persisted occurrence
   *
   * @return MaintenanceOccurrenceState exact persisted attempt with UTC execution dates
   */
  public function occurrence(array $row): MaintenanceOccurrenceState
  {
    return new MaintenanceOccurrenceState((string) $row['id'], (string) $row['plan_id'], (string) $row['organization_id'], new DateTimeImmutable((string) $row['due_at'], new DateTimeZone('UTC')), (string) $row['status'], (int) $row['attempt'], null === $row['intervention_id'] ? null : (string) $row['intervention_id'], $this->parsed($row['completed_at']), null === $row['result_id'] ? null : (string) $row['result_id'], new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')), new DateTimeImmutable((string) $row['updated_at'], new DateTimeZone('UTC')), null === $row['number'] ? null : (int) $row['number']);
  }

  /**
   * Method parsed
   *
   * @access private
   *
   * @param bool|int|string|null $date nullable DBAL value using the historical scalar conversion
   *
   * @return DateTimeImmutable|null stored instant in UTC, or an absent historical date
   */
  private function parsed(bool|int|string|null $date): ?DateTimeImmutable
  {
    return null === $date ? null : new DateTimeImmutable((string) $date, new DateTimeZone('UTC'));
  }
  // #endregion
}
