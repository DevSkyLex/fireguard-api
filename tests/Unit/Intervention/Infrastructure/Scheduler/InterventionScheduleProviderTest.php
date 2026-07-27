<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Scheduler;

use Intervention\Application\UseCase\Command\Sweep\MaterializeDueRecurrences\MaterializeDueRecurrencesCommand;
use Intervention\Infrastructure\Scheduler\InterventionScheduleProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\{PeriodicalTrigger, StaticMessageProvider};

/**
 * Test InterventionScheduleProviderTest.
 *
 * Materialising due recurrences creates real interventions, so a second
 * worker running the sweep concurrently would duplicate them. The lock and
 * the stateful cache are what prevent that, and neither is visible at
 * runtime until it fails.
 *
 * @category Scheduler Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionScheduleProvider::class)]
final class InterventionScheduleProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testScheduleDispatchesTheRecurrenceSweep(): void
  {
    $schedule = $this->createProvider()->getSchedule();

    self::assertInstanceOf(Schedule::class, $schedule);

    $messages = $schedule->getRecurringMessages();

    self::assertCount(1, $messages);

    $provider = $messages[0]->getProvider();
    self::assertInstanceOf(StaticMessageProvider::class, $provider);
    self::assertStringContainsString(MaterializeDueRecurrencesCommand::class, (string) $provider);
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

  private function createProvider(): InterventionScheduleProvider
  {
    return new InterventionScheduleProvider(
      cache: new ArrayAdapter(),
      lockFactory: new LockFactory(new InMemoryStore()),
    );
  }
  // #endregion
}
