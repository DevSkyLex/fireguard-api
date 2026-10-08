<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Maintenance\Domain\Model\MaintenancePlan;
use Maintenance\Domain\ValueObject\{MaintenanceOperationKind, MaintenancePlanCalendar, MaintenancePlanIdentity, PlanCadence};
use PHPUnit\Framework\Attributes\{CoversClass, Test, UsesClass};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;

/**
 * Class MaintenancePlanTest
 *
 * Verifies independent equipment operations, suspension and preserved calendars.
 *
 * @category Tests
 */
#[CoversClass(MaintenancePlan::class)]
#[UsesClass(PlanCadence::class)]
#[UsesClass(MaintenancePlanCalendar::class)]
#[UsesClass(MaintenancePlanIdentity::class)]
final class MaintenancePlanTest extends TestCase
{
  // #region Methods
  /**
   * Method testCompletingAnOperationDoesNotMoveOtherPlans
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCompletingAnOperationDoesNotMoveOtherPlans(): void
  {
    $control = $this->plan('P1Y', MaintenanceOperationKind::CONTROL);
    $maintenance = $this->plan('P1M', MaintenanceOperationKind::MAINTENANCE);

    $maintenance->complete(new DateTimeImmutable('2026-02-03T00:00:00+00:00'));

    self::assertSame('2026-02-28', $maintenance->nextDueAt()?->format('Y-m-d'));
    self::assertSame('2026-01-31', $control->nextDueAt()?->format('Y-m-d'));
  }

  /**
   * Method testLateCompletionSkipsPastSlotsAndRetainsAnchor
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testLateCompletionSkipsPastSlotsAndRetainsAnchor(): void
  {
    $plan = $this->plan('P1M');

    $plan->complete(new DateTimeImmutable('2026-04-15T00:00:00+00:00'));

    self::assertSame('2026-04-30', $plan->nextDueAt()?->format('Y-m-d'));
    self::assertSame('2026-01-31', $plan->anchorAt?->format('Y-m-d'));
    self::assertSame(['2026-04-30', '2026-05-31', '2026-06-30'], array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'), $plan->preview()));
  }

  /**
   * Method testEarlyCompletionAdvancesItsDueSlot
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testEarlyCompletionAdvancesItsDueSlot(): void
  {
    $plan = $this->plan('P1M');

    $plan->complete(new DateTimeImmutable('2026-01-29T00:00:00+00:00'));

    self::assertSame('2026-02-28', $plan->nextDueAt()?->format('Y-m-d'));
  }

  /**
   * Method testLegacyUnscheduledPlanStartsFromFirstValidation
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testLegacyUnscheduledPlanStartsFromFirstValidation(): void
  {
    $plan = MaintenancePlan::create(
      $this->identity(),
      'Historical inspection',
      MaintenanceOperationKind::CONTROL,
      PlanCadence::legacyFromString('P1M'),
      null,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      true,
    );

    self::assertNull($plan->nextDueAt());
    self::assertSame([], $plan->preview());
    self::assertFalse($plan->canGenerate(false, false));

    $plan->complete(new DateTimeImmutable('2026-01-31T00:00:00+00:00'));

    self::assertSame('2026-03-03', $plan->nextDueAt()?->format('Y-m-d'));
    self::assertTrue($plan->canGenerate(false, false));
  }

  /**
   * Method testGenerationIsSuspendedBySiteEquipmentAndPlanLifecycle
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testGenerationIsSuspendedBySiteEquipmentAndPlanLifecycle(): void
  {
    $plan = $this->plan('P1Y');

    self::assertTrue($plan->canGenerate(false, false));
    self::assertFalse($plan->canGenerate(true, false));
    self::assertFalse($plan->canGenerate(false, true));

    $plan->archive();

    self::assertTrue($plan->isArchived());
    self::assertFalse($plan->canGenerate(false, false));
    self::assertSame('2026-01-31', $plan->nextDueAt()?->format('Y-m-d'));
    $plan->complete(new DateTimeImmutable('2026-01-31'));

    self::assertSame('2027-01-31', $plan->nextDueAt()?->format('Y-m-d'));
    self::assertFalse($plan->canGenerate(false, false));
  }

  /**
   * Method testRestorationKeepsAnchorAndDueDateSeparate
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRestorationKeepsAnchorAndDueDateSeparate(): void
  {
    $plan = MaintenancePlan::reconstitute(
      $this->identity(),
      'Monthly service',
      MaintenanceOperationKind::MAINTENANCE,
      new MaintenancePlanCalendar(
        PlanCadence::fromString('P1M'),
        new DateTimeImmutable('2026-01-31T00:00:00+00:00'),
        new DateTimeImmutable('2026-02-28T00:00:00+00:00'),
        false,
      ),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      false,
    );

    self::assertSame(['2026-02-28', '2026-03-31', '2026-04-30'], array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'), $plan->preview()));
  }

  /**
   * Method testRestorationRejectsDueDateOutsideAnchoredCalendar
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRestorationRejectsDueDateOutsideAnchoredCalendar(): void
  {
    $this->expectException(InvalidValueException::class);

    MaintenancePlan::reconstitute(
      $this->identity(),
      'Monthly service',
      MaintenanceOperationKind::MAINTENANCE,
      new MaintenancePlanCalendar(
        PlanCadence::fromString('P1M'),
        new DateTimeImmutable('2026-01-31T00:00:00+00:00'),
        new DateTimeImmutable('2026-02-15T00:00:00+00:00'),
        false,
      ),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      false,
    );
  }

  /**
   * Method testRestorationAcceptsLeapDayClampWithoutChangingAnchor
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRestorationAcceptsLeapDayClampWithoutChangingAnchor(): void
  {
    $plan = MaintenancePlan::reconstitute(
      $this->identity(),
      'Annual control',
      MaintenanceOperationKind::CONTROL,
      new MaintenancePlanCalendar(
        PlanCadence::fromString('P1Y'),
        new DateTimeImmutable('2024-02-29T00:00:00+00:00'),
        new DateTimeImmutable('2025-02-28T00:00:00+00:00'),
        false,
      ),
      new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
      false,
    );

    self::assertSame('2024-02-29', $plan->anchorAt?->format('Y-m-d'));
    self::assertSame(['2025-02-28', '2026-02-28', '2027-02-28', '2028-02-29'], array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'), $plan->preview(4)));
  }

  /**
   * Method testRestorationRetainsArchivedStateAndRehydratedCalendarTimezone
   *
   * Restored plans preserve their exact persisted name, ownership and calendar
   * after UTC dates have been returned to the plan's frozen timezone.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRestorationRetainsArchivedStateAndRehydratedCalendarTimezone(): void
  {
    $timezone = new DateTimeZone('Europe/Paris');
    $anchor = new DateTimeImmutable('2026-01-31T00:00:00', $timezone);
    $due = new DateTimeImmutable('2026-03-31T00:00:00', $timezone);
    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $identity = $this->identity();
    $plan = MaintenancePlan::reconstitute(
      $identity,
      ' Saved operation ',
      MaintenanceOperationKind::MAINTENANCE,
      new MaintenancePlanCalendar(PlanCadence::fromString('P1M'), $anchor, $due, false),
      $createdAt,
      true,
    );

    self::assertSame($identity->id, $plan->id);
    self::assertSame($identity->organizationId, $plan->organizationId);
    self::assertSame($identity->equipmentId, $plan->equipmentId);
    self::assertSame(' Saved operation ', $plan->name);
    self::assertSame($createdAt, $plan->createdAt);
    self::assertSame($anchor, $plan->anchorAt);
    self::assertSame($due, $plan->nextDueAt());
    self::assertFalse($plan->legacy);
    self::assertTrue($plan->isArchived());
    self::assertFalse($plan->canGenerate(false, false));
    self::assertSame(['2026-03-31T00:00:00+02:00', '2026-04-30T00:00:00+02:00', '2026-05-31T00:00:00+02:00'], array_map(static fn (DateTimeImmutable $date): string => $date->format('c'), $plan->preview()));
  }

  /**
   * Method testFixedPlanRequiresFirstDueDate
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFixedPlanRequiresFirstDueDate(): void
  {
    $this->expectException(InvalidValueException::class);

    MaintenancePlan::create(
      $this->identity(),
      'Inspection',
      MaintenanceOperationKind::CONTROL,
      PlanCadence::fromString('P1Y'),
      null,
      new DateTimeImmutable('2026-01-01'),
    );
  }

  /**
   * Method testLegacyFlagMustMatchCadence
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testLegacyFlagMustMatchCadence(): void
  {
    $this->expectException(InvalidValueException::class);

    MaintenancePlan::create(
      $this->identity(),
      'Inspection',
      MaintenanceOperationKind::CONTROL,
      PlanCadence::legacyFromString('P1M'),
      new DateTimeImmutable('2026-01-31'),
      new DateTimeImmutable('2026-01-01'),
    );
  }

  /**
   * Method plan
   *
   * Creates one operation against the same equipment for independence assertions.
   *
   * @access private
   *
   * @param string $interval the fixed cadence
   * @param MaintenanceOperationKind $kind the operation kind
   *
   * @return MaintenancePlan the plan under test
   */
  private function plan(string $interval, MaintenanceOperationKind $kind = MaintenanceOperationKind::MAINTENANCE): MaintenancePlan
  {
    return MaintenancePlan::create(
      $this->identity(),
      'Monthly operation',
      $kind,
      PlanCadence::fromString($interval),
      new DateTimeImmutable('2026-01-31T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }

  /**
   * Method identity
   *
   * Returns the same validated ownership scope for independent operation tests.
   *
   * @access private
   *
   * @return MaintenancePlanIdentity the plan ownership scope
   */
  private function identity(): MaintenancePlanIdentity
  {
    return new MaintenancePlanIdentity(
      '018fa001-1111-7111-8111-111111111111',
      '018fa002-1111-7111-8111-111111111111',
      '018fa003-1111-7111-8111-111111111111',
    );
  }
  // #endregion
}
