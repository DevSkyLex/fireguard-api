<?php

declare(strict_types=1);

namespace Tests\Unit\Workload\Domain\Model\Capacity;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Workload\Domain\Model\Capacity\CapacitySchedule;
use Workload\Domain\ValueObject\{CapacityChange, CapacityException, CapacityWeek, LocalDate};

final class CapacityScheduleTest extends TestCase
{
  public function testNoImplicitNationalWeek(): void
  {
    self::assertNull(new CapacitySchedule()->on(LocalDate::fromString('2026-09-16')));
  }

  public function testEffectiveMemberWeekReplacesOrganizationWeek(): void
  {
    $org = new CapacityChange(LocalDate::fromString('2026-01-01'), new CapacityWeek([420, 420, 420, 420, 420, 0, 0]));
    $member = new CapacityChange(LocalDate::fromString('2026-09-16'), new CapacityWeek([210, 210, 210, 210, 0, 0, 0]));
    $schedule = new CapacitySchedule([$org], [$member]);
    self::assertSame(420, $schedule->on(LocalDate::fromString('2026-09-15')));
    self::assertSame(210, $schedule->on(LocalDate::fromString('2026-09-16')));
    self::assertSame(0, $schedule->on(LocalDate::fromString('2026-09-18')));
  }

  public function testDatedAbsenceOverridesWeek(): void
  {
    $day = LocalDate::fromString('2026-09-16');
    $schedule = new CapacitySchedule([new CapacityChange($day, new CapacityWeek([420, 420, 420, 420, 420, 0, 0]))], [], [new CapacityException($day, $day, 0)]);
    self::assertSame(0, $schedule->on($day));
  }

  public function testContradictoryExceptionsAreRejected(): void
  {
    $this->expectException(InvalidValueException::class);
    $day = LocalDate::fromString('2026-09-16');
    new CapacitySchedule([], [], [new CapacityException($day, $day, 0), new CapacityException($day, $day, 120)]);
  }
}
