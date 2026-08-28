<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Organization\Application\UseCase\Command\Sweep\SendWeeklyDigests\SendWeeklyDigestsCommand;
use Organization\Infrastructure\Scheduler\OrganizationScheduleProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\{PeriodicalTrigger, StaticMessageProvider};

/**
 * Test OrganizationScheduleProviderTest.
 *
 * The weekly digest is the only weekly schedule in the app: it must fire
 * weekly on Monday mornings UTC (not hourly), stay stateful across
 * restarts, and stay locked against overlapping workers.
 *
 * @category Scheduler Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationScheduleProvider::class)]
final class OrganizationScheduleProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testScheduleDispatchesTheWeeklyDigestSweep(): void
  {
    $schedule = $this->createProvider()->getSchedule();

    self::assertInstanceOf(Schedule::class, $schedule);

    $messages = $schedule->getRecurringMessages();

    self::assertCount(1, $messages);

    $provider = $messages[0]->getProvider();
    self::assertInstanceOf(StaticMessageProvider::class, $provider);
    self::assertStringContainsString(SendWeeklyDigestsCommand::class, (string) $provider);
  }

  #[Test]
  public function testSweepRunsWeeklyOnMondayMorningUtc(): void
  {
    $messages = $this->createProvider()->getSchedule()->getRecurringMessages();

    $trigger = $messages[0]->getTrigger();
    self::assertInstanceOf(PeriodicalTrigger::class, $trigger);

    $nextRun = $trigger->getNextRunDate(new DateTimeImmutable('2026-09-02T12:00:00+00:00'));
    self::assertSame('2026-09-07 06:00:00 Monday', $nextRun?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s l'));
  }

  #[Test]
  public function testScheduleIsStatefulAndLockGuarded(): void
  {
    $schedule = $this->createProvider()->getSchedule();

    self::assertNotNull($schedule->getState(), 'The schedule must be stateful to survive a restart.');
    self::assertNotNull($schedule->getLock(), 'The schedule must be locked against concurrent sweeps.');
  }

  private function createProvider(): OrganizationScheduleProvider
  {
    return new OrganizationScheduleProvider(
      cache: new ArrayAdapter(),
      lockFactory: new LockFactory(new InMemoryStore()),
    );
  }
  // #endregion
}
