<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Infrastructure\Persistence\Doctrine\Mapper;

use Maintenance\Infrastructure\Persistence\Doctrine\Mapper\MaintenancePlanStateMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const DATE_ATOM;

/**
 * Class MaintenancePlanStateMapperTest
 *
 * Pins the persisted contract independently of creation validation and calendar projections.
 *
 * @category Test
 */
final class MaintenancePlanStateMapperTest extends TestCase
{
  // #region Methods
  /**
   * Method planPreservesAllFieldsAndBothParisCalendarOffsets
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function planPreservesAllFieldsAndBothParisCalendarOffsets(): void
  {
    $row = $this->planRow();
    $mapper = new MaintenancePlanStateMapper();
    $plan = $mapper->plan($row);

    self::assertNotNull($plan->anchorAt);
    self::assertNotNull($plan->nextDueAt);
    self::assertSame('2027-01-31T00:00:00+01:00', $plan->anchorAt->format(DATE_ATOM));
    self::assertSame('2027-03-31T00:00:00+02:00', $plan->nextDueAt->format(DATE_ATOM));
    self::assertSame('Europe/Paris', $plan->anchorAt->getTimezone()->getName());
    self::assertSame('Europe/Paris', $plan->nextDueAt->getTimezone()->getName());
    self::assertSame('UTC', $plan->lastCompletedAt?->getTimezone()->getName());
    self::assertSame('UTC', $plan->archivedAt?->getTimezone()->getName());
    self::assertSame('UTC', $plan->createdAt->getTimezone()->getName());
    self::assertSame('UTC', $plan->updatedAt->getTimezone()->getName());
    self::assertSame($row, $mapper->snapshot($plan));
    self::assertSame('2027-01-31T00:00:00+01:00', $plan->anchorAt->format(DATE_ATOM));
    self::assertSame('2027-03-31T00:00:00+02:00', $plan->nextDueAt->format(DATE_ATOM));
  }

  /**
   * Method historicalUninitializedPlanKeepsRawConfigurationAndAbsentDates
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function historicalUninitializedPlanKeepsRawConfigurationAndAbsentDates(): void
  {
    $row = $this->planRow();
    $row['facility_id'] = null;
    $row['operation_kind'] = 'historical_control';
    $row['interval'] = 'P40Y';
    $row['cadence_mode'] = 'legacy';
    $row['anchor_at'] = null;
    $row['next_due_at'] = null;
    $row['active'] = false;
    $row['legacy_schedule_id'] = null;
    $row['last_completed_at'] = null;
    $row['archived_at'] = null;
    $row['calendar_timezone'] = 'UTC';
    $mapper = new MaintenancePlanStateMapper();
    $plan = $mapper->plan($row);

    self::assertSame('historical_control', $plan->operationKind);
    self::assertSame('P40Y', $plan->interval);
    self::assertFalse($plan->active);
    self::assertNull($plan->facilityId);
    self::assertNull($plan->anchorAt);
    self::assertNull($plan->nextDueAt);
    self::assertNull($plan->legacyScheduleId);
    self::assertNull($plan->lastCompletedAt);
    self::assertNull($plan->archivedAt);
    self::assertSame($row, $mapper->snapshot($plan));
  }

  /**
   * Method occurrencePreservesCompletedAttemptResultAndOriginalDueInstant
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function occurrencePreservesCompletedAttemptResultAndOriginalDueInstant(): void
  {
    $row = $this->occurrenceRow();
    $mapper = new MaintenancePlanStateMapper();
    $occurrence = $mapper->occurrence($row);

    self::assertSame('2027-03-30T22:00:00+00:00', $occurrence->dueAt->format(DATE_ATOM));
    self::assertSame('UTC', $occurrence->dueAt->getTimezone()->getName());
    self::assertSame('UTC', $occurrence->completedAt?->getTimezone()->getName());
    self::assertSame('UTC', $occurrence->createdAt->getTimezone()->getName());
    self::assertSame('UTC', $occurrence->updatedAt->getTimezone()->getName());
    self::assertSame(3, $occurrence->attempt);
    self::assertSame('intervention-historical', $occurrence->interventionId);
    self::assertSame('result-historical', $occurrence->resultId);
    self::assertSame(0, $occurrence->number);
    self::assertSame($row, $mapper->snapshot($occurrence));
  }

  /**
   * Method uninitializedOccurrenceKeepsAbsentWorkResultAndNumber
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function uninitializedOccurrenceKeepsAbsentWorkResultAndNumber(): void
  {
    $row = $this->occurrenceRow();
    $row['status'] = 'open';
    $row['attempt'] = 0;
    $row['intervention_id'] = null;
    $row['completed_at'] = null;
    $row['result_id'] = null;
    $row['number'] = null;
    $mapper = new MaintenancePlanStateMapper();
    $occurrence = $mapper->occurrence($row);

    self::assertSame(0, $occurrence->attempt);
    self::assertNull($occurrence->interventionId);
    self::assertNull($occurrence->completedAt);
    self::assertNull($occurrence->resultId);
    self::assertNull($occurrence->number);
    self::assertSame($row, $mapper->snapshot($occurrence));
  }

  /**
   * Method planRow
   *
   * @access private
   *
   * @return array<string, bool|int|string|null> complete accepted historical storage row
   */
  private function planRow(): array
  {
    return [
      'id' => 'plan-historical',
      'organization_id' => 'organization-historical',
      'equipment_id' => 'equipment-historical',
      'facility_id' => 'facility-historical',
      'equipment_type' => 'fire_extinguisher',
      'name' => 'Original annual control',
      'operation_kind' => 'control',
      'interval' => 'P1M',
      'cadence_mode' => 'fixed',
      'anchor_at' => '2027-01-30 23:00:00',
      'next_due_at' => '2027-03-30 22:00:00',
      'active' => true,
      'legacy_schedule_id' => 'schedule-historical',
      'last_completed_at' => '2027-02-28 12:34:56',
      'archived_at' => '2027-03-01 01:02:03',
      'created_at' => '2026-10-06 09:08:07',
      'updated_at' => '2027-03-01 04:05:06',
      'calendar_timezone' => 'Europe/Paris',
    ];
  }

  /**
   * Method occurrenceRow
   *
   * @access private
   *
   * @return array<string, bool|int|string|null> complete historical attempt and lineage
   */
  private function occurrenceRow(): array
  {
    return [
      'id' => 'occurrence-historical',
      'plan_id' => 'plan-historical',
      'organization_id' => 'organization-historical',
      'due_at' => '2027-03-30 22:00:00',
      'status' => 'completed',
      'attempt' => 3,
      'intervention_id' => 'intervention-historical',
      'completed_at' => '2027-04-01 11:22:33',
      'result_id' => 'result-historical',
      'created_at' => '2027-03-20 09:08:07',
      'updated_at' => '2027-04-01 11:22:34',
      'number' => 0,
    ];
  }
  // #endregion
}
