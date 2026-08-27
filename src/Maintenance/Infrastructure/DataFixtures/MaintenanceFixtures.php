<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\DataFixtures;

use DateInterval;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Equipment\Infrastructure\DataFixtures\EquipmentFixtures;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy;
use Maintenance\Domain\ValueObject\MaintenanceDueStatus;
use Maintenance\Infrastructure\Persistence\Doctrine\Record\MaintenanceScheduleRecord;
use Organization\Infrastructure\DataFixtures\OrganizationFixtures;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Infrastructure\DataFixtures\{SeedTimeline, SeedUuid};

use function sprintf;

/**
 * DataFixtures MaintenanceFixtures.
 *
 * Seeds one maintenance schedule per tracked piece of equipment. Without
 * these rows the whole compliance register reads as empty: the Compliance
 * module derives `trackedEquipmentCount`, `upToDate/dueSoon/overdue` and
 * therefore `complianceRate` exclusively from
 * `maintenance_schedules.due_status`
 * (see `MaintenanceComplianceStatisticsAdapter`), and the Equipment KPI
 * tiles read the same due statuses through `MaintenanceDueStatusPort`.
 *
 * Every date is derived from "now", and `nextDueAt` / `dueStatus` are kept
 * mutually consistent with {@see MaintenanceScheduleRecomputePolicy} so the
 * hourly recompute sweep confirms the seeded state rather than contradicting
 * it. Decommissioned equipment gets no schedule, mirroring the sweep's
 * decommission cleanup.
 *
 * @category DataFixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MaintenanceFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
  // #region Constants
  /**
   * Persisted Equipment status used to omit decommissioned assets.
   *
   * Fixtures may depend on Equipment fixture records, but must not import
   * another module's Domain layer merely to compare a persisted scalar.
   */
  private const string DECOMMISSIONED_EQUIPMENT_STATUS = 'decommissioned';

  /**
   * Constant OVERDUE_MODULUS.
   *
   * One schedule in nineteen is overdue, one in thirteen of the remainder is
   * due soon: a deliberately realistic spread that lands the organization
   * compliance rate in the mid-to-high eighties rather than a meaningless
   * 0% or 100%.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int OVERDUE_MODULUS = 19;

  /**
   * Constant DUE_SOON_MODULUS.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int DUE_SOON_MODULUS = 13;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @param MaintenanceCompliancePolicyPort $compliancePolicies the organization compliance-policy port
   */
  public function __construct(
    private readonly MaintenanceCompliancePolicyPort $compliancePolicies,
  ) {
  }
  // #endregion

  // #region Methods
  public static function getGroups(): array
  {
    return ['maintenance', 'main-seed'];
  }

  public function getDependencies(): array
  {
    return [
      OrganizationFixtures::class,
      EquipmentFixtures::class,
    ];
  }

  public function load(ObjectManager $manager): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->getReference(OrganizationFixtures::ORGANIZATION_REFERENCE, OrganizationRecord::class);

    $policy = new MaintenanceScheduleRecomputePolicy();
    $compliancePolicy = $this->compliancePolicies->compliancePolicy($organization->id);
    $reminderWindowDays = $compliancePolicy->reminderWindowDays;
    $now = SeedTimeline::now();

    $index = 0;
    foreach ($this->equipmentReferences() as $reference) {
      /** @var EquipmentRecord $equipment */
      $equipment = $this->getReference($reference, EquipmentRecord::class);

      // The sweep drops schedules for decommissioned assets; seeding one
      // would immediately be cleaned up and would inflate the tracked count.
      if (self::DECOMMISSIONED_EQUIPMENT_STATUS === $equipment->status) {
        continue;
      }

      $periodicity = $compliancePolicy->periodicityFor($equipment->type);
      $interval = $policy->resolveEffectiveInterval(null, $periodicity);

      // Walk the target due date BACK through the periodicity to get the
      // closure date, then let the policy walk it forward again: the seeded
      // pair is then exactly what the recompute sweep would produce.
      $lastInspectionClosedAt = null === $interval || null === $periodicity
        ? null
        : $this->targetNextDueAtFor($index, $reminderWindowDays)->sub(new DateInterval($periodicity));
      $nextDueAt = $policy->computeNextDueAt($lastInspectionClosedAt, $interval);

      $schedule = new MaintenanceScheduleRecord();
      $schedule->id = SeedUuid::from(sprintf('maintenance-schedule:%s', $reference));
      $schedule->organization = $organization;
      $schedule->equipmentId = $equipment->id;
      $schedule->facilityId = $equipment->facilityId;
      $schedule->equipmentType = $equipment->type;
      $schedule->intervalOverride = null;
      $schedule->lastInspectionClosedAt = $lastInspectionClosedAt;
      $schedule->nextDueAt = $nextDueAt;
      $schedule->dueStatus = $policy->computeDueStatus($nextDueAt, $interval, $now, $reminderWindowDays)->value;
      $schedule->lastRemindedAt = MaintenanceDueStatus::DUE_SOON->value === $schedule->dueStatus
        ? $now->modify('-1 day')
        : null;
      $schedule->remindedFor = MaintenanceDueStatus::DUE_SOON->value === $schedule->dueStatus ? $nextDueAt : null;
      $schedule->createdAt = $lastInspectionClosedAt ?? $now->modify('-1 year');
      $schedule->updatedAt = $now->modify('-1 hour');

      $manager->persist($schedule);
      ++$index;
    }

    $manager->flush();
  }

  /**
   * Method targetNextDueAtFor.
   *
   * Places the next due date so the resulting due status matches the target
   * spread: mostly comfortably in the future, a handful inside the reminder
   * window, a few already past.
   *
   * @since 1.0.0
   *
   * @param int $index the schedule index in the seeded sequence
   * @param int $reminderWindowDays the organization reminder window, in days
   *
   * @return DateTimeImmutable the seeded next due date
   */
  private function targetNextDueAtFor(int $index, int $reminderWindowDays): DateTimeImmutable
  {
    if (0 === $index % self::OVERDUE_MODULUS) {
      // Past due by a varying amount, so the overdue list is not all one age.
      return SeedTimeline::fromNow(-(3 + ($index % 47)), 10);
    }

    if (0 === $index % self::DUE_SOON_MODULUS) {
      // Strictly inside the reminder window, never today, never past it.
      return SeedTimeline::fromNow(2 + ($index % ($reminderWindowDays - 4)), 10);
    }

    // Comfortably beyond the reminder window.
    return SeedTimeline::fromNow($reminderWindowDays + 15 + ($index % 220), 10);
  }

  /**
   * Method equipmentReferences.
   *
   * Every equipment fixture reference, hand-written assets first then the
   * bulk and regional seeds, so schedule identifiers stay stable across runs.
   *
   * @since 1.0.0
   *
   * @return list<string> the equipment fixture references
   */
  private function equipmentReferences(): array
  {
    $references = [
      EquipmentFixtures::EXTINGUISHER_REFERENCE,
      EquipmentFixtures::DETECTOR_REFERENCE,
      EquipmentFixtures::HYDRANT_REFERENCE,
      EquipmentFixtures::SPRINKLER_REFERENCE,
      EquipmentFixtures::ALARM_PANEL_REFERENCE,
      EquipmentFixtures::HEAT_DETECTOR_REFERENCE,
      EquipmentFixtures::SITE_EMERGENCY_LIGHTING_REFERENCE,
      EquipmentFixtures::BUILDING_FIRE_DOOR_REFERENCE,
      EquipmentFixtures::FLOOR_ONE_CAMERA_REFERENCE,
      EquipmentFixtures::FLOOR_TWO_GAS_DETECTOR_REFERENCE,
    ];

    foreach (EquipmentFixtures::ADDITIONAL_EQUIPMENT_SEEDS as $seed) {
      $references[] = $seed['reference'];
    }

    // The dozen extra regional sites EquipmentFixtures added purely for
    // pagination volume: without a schedule here, every one of those assets
    // would be non-decommissioned but untracked, which the compliance
    // register's `trackedEquipmentCount` treats as a state the seed should
    // never produce.
    for ($index = 0; $index < EquipmentFixtures::EXTRA_REGIONAL_EQUIPMENT_COUNT; ++$index) {
      $references[] = EquipmentFixtures::extraRegionalEquipmentReference($index);
    }

    return $references;
  }
  // #endregion
}
