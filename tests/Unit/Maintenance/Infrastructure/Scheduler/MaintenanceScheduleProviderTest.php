<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Infrastructure\Scheduler;

use Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules\RecomputeMaintenanceSchedulesCommand;
use Maintenance\Infrastructure\Scheduler\MaintenanceScheduleProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\{PeriodicalTrigger, StaticMessageProvider};

/**
 * Test MaintenanceScheduleProviderTest.
 *
 * Recomputing maintenance schedules drives the due-date reporting the whole
 * compliance surface reads, so the sweep must stay stateful across restarts
 * and locked against overlapping workers.
 *
 * @category Scheduler Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceScheduleProvider::class)]
final class MaintenanceScheduleProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testScheduleDispatchesTheRecomputeSweep(): void
  {
    $schedule = $this->createProvider()->getSchedule();

    self::assertInstanceOf(Schedule::class, $schedule);

    $messages = $schedule->getRecurringMessages();

    self::assertCount(1, $messages);

    $provider = $messages[0]->getProvider();
    self::assertInstanceOf(StaticMessageProvider::class, $provider);
    self::assertStringContainsString(RecomputeMaintenanceSchedulesCommand::class, (string) $provider);
  }

  #[Test]
  public function testSweepRunsOnAPeriodicalTrigger(): void
  {
    $messages = $this->createProvider()->getSchedule()->getRecurringMessages();

    self::assertInstanceOf(PeriodicalTrigger::class, $messages[0]->getTrigger());
  }

  #[Test]
  public function testScheduleIsStatefulAndLockGuarded(): void
  {
    $schedule = $this->createProvider()->getSchedule();

    self::assertNotNull($schedule->getState(), 'The schedule must be stateful to survive a restart.');
    self::assertNotNull($schedule->getLock(), 'The schedule must be locked against concurrent sweeps.');
  }

  private function createProvider(): MaintenanceScheduleProvider
  {
    return new MaintenanceScheduleProvider(
      cache: new ArrayAdapter(),
      lockFactory: new LockFactory(new InMemoryStore()),
    );
  }
  // #endregion
}
