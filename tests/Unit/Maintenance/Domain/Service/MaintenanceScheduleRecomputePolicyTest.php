<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\Service;

use DateTimeImmutable;
use Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy;
use Maintenance\Domain\ValueObject\{MaintenanceDueStatus, PeriodicityInterval};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceScheduleRecomputePolicyTest.
 *
 * @category Domain Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceScheduleRecomputePolicy::class)]
final class MaintenanceScheduleRecomputePolicyTest extends TestCase
{
  private MaintenanceScheduleRecomputePolicy $policy;

  protected function setUp(): void
  {
    $this->policy = new MaintenanceScheduleRecomputePolicy();
  }

  // #region Effective interval resolution

  #[Test]
  public function testResolveEffectiveIntervalPrefersOverrideOverOrganizationPolicy(): void
  {
    $interval = $this->policy->resolveEffectiveInterval('P30D', 'P90D');

    self::assertSame('P30D', $interval?->value);
  }

  #[Test]
  public function testResolveEffectiveIntervalFallsBackToOrganizationPolicyWhenNoOverride(): void
  {
    $interval = $this->policy->resolveEffectiveInterval(null, 'P90D');

    self::assertSame('P90D', $interval?->value);
  }

  #[Test]
  public function testResolveEffectiveIntervalIsNullWhenNeitherIsSet(): void
  {
    self::assertNull($this->policy->resolveEffectiveInterval(null, null));
  }

  // #endregion

  // #region Next due date

  #[Test]
  public function testComputeNextDueAtIsNullWithoutEffectiveInterval(): void
  {
    self::assertNull($this->policy->computeNextDueAt(new DateTimeImmutable('2026-01-01'), null));
  }

  #[Test]
  public function testComputeNextDueAtIsNullWithoutLastInspection(): void
  {
    self::assertNull($this->policy->computeNextDueAt(null, PeriodicityInterval::fromString('P90D')));
  }

  #[Test]
  public function testComputeNextDueAtAddsTheIntervalToTheLastInspection(): void
  {
    $nextDueAt = $this->policy->computeNextDueAt(
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      PeriodicityInterval::fromString('P90D'),
    );

    self::assertSame('2026-04-01T00:00:00+00:00', $nextDueAt?->format('c'));
  }

  // #endregion

  // #region Due status transitions

  #[Test]
  public function testComputeDueStatusIsUnscheduledWithoutEffectiveInterval(): void
  {
    $status = $this->policy->computeDueStatus(null, null, new DateTimeImmutable(), 14);

    self::assertSame(MaintenanceDueStatus::UNSCHEDULED, $status);
  }

  #[Test]
  public function testComputeDueStatusIsOverdueWhenNeverInspectedButPeriodicityApplies(): void
  {
    $status = $this->policy->computeDueStatus(
      null,
      PeriodicityInterval::fromString('P90D'),
      new DateTimeImmutable('2026-01-01'),
      14,
    );

    self::assertSame(MaintenanceDueStatus::OVERDUE, $status);
  }

  #[Test]
  public function testComputeDueStatusIsUpToDateWellBeforeTheDueDate(): void
  {
    $status = $this->policy->computeDueStatus(
      new DateTimeImmutable('2026-06-01'),
      PeriodicityInterval::fromString('P90D'),
      new DateTimeImmutable('2026-01-01'),
      14,
    );

    self::assertSame(MaintenanceDueStatus::UP_TO_DATE, $status);
  }

  #[Test]
  public function testComputeDueStatusIsDueSoonInsideTheReminderWindow(): void
  {
    $status = $this->policy->computeDueStatus(
      new DateTimeImmutable('2026-01-10'),
      PeriodicityInterval::fromString('P90D'),
      new DateTimeImmutable('2026-01-01'),
      14,
    );

    self::assertSame(MaintenanceDueStatus::DUE_SOON, $status);
  }

  #[Test]
  public function testComputeDueStatusIsOverdueAfterTheDueDate(): void
  {
    $status = $this->policy->computeDueStatus(
      new DateTimeImmutable('2026-01-01'),
      PeriodicityInterval::fromString('P90D'),
      new DateTimeImmutable('2026-01-02'),
      14,
    );

    self::assertSame(MaintenanceDueStatus::OVERDUE, $status);
  }

  // #endregion

  // #region Reminder re-arming (idempotence)

  #[Test]
  public function testShouldResetRemindedForIsFalseWhenNextDueAtDidNotChange(): void
  {
    $date = new DateTimeImmutable('2026-04-01T00:00:00+00:00');

    self::assertFalse($this->policy->shouldResetRemindedFor($date, new DateTimeImmutable('2026-04-01T00:00:00+00:00')));
  }

  #[Test]
  public function testShouldResetRemindedForIsTrueWhenNextDueAtChanged(): void
  {
    self::assertTrue($this->policy->shouldResetRemindedFor(
      new DateTimeImmutable('2026-04-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-05-01T00:00:00+00:00'),
    ));
  }

  #[Test]
  public function testShouldResetRemindedForIsTrueWhenTransitioningFromNull(): void
  {
    self::assertTrue($this->policy->shouldResetRemindedFor(null, new DateTimeImmutable('2026-04-01T00:00:00+00:00')));
  }

  #[Test]
  public function testShouldResetRemindedForIsFalseWhenBothAreNull(): void
  {
    self::assertFalse($this->policy->shouldResetRemindedFor(null, null));
  }

  // #endregion
}
