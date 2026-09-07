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
use Symfony\Component\Scheduler\{RecurringMessage, Schedule};
use Symfony\Component\Scheduler\Trigger\{PeriodicalTrigger, StaticMessageProvider};

use function array_filter;
use function array_map;
use function array_values;
use function str_contains;

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

    self::assertCount(2, $messages);

    $providers = array_map(self::scheduledCommandName(...), $messages);
    self::assertTrue((bool) array_filter($providers, static fn (string $name): bool => str_contains($name, \Organization\Application\UseCase\Command\Sweep\VerifyOrganizationDomains\VerifyOrganizationDomainsCommand::class)));
    $weekly = array_values(array_filter($messages, static fn (RecurringMessage $message): bool => str_contains(self::scheduledCommandName($message), SendWeeklyDigestsCommand::class)));
    self::assertCount(1, $weekly);
    $provider = $weekly[0]->getProvider();
    self::assertInstanceOf(StaticMessageProvider::class, $provider);
    self::assertStringContainsString(SendWeeklyDigestsCommand::class, (string) $provider);
  }

  #[Test]
  public function testSweepRunsWeeklyOnMondayMorningUtc(): void
  {
    $messages = $this->createProvider()->getSchedule()->getRecurringMessages();

    $weekly = array_values(array_filter($messages, static fn (RecurringMessage $message): bool => str_contains(self::scheduledCommandName($message), SendWeeklyDigestsCommand::class)));
    self::assertCount(1, $weekly);
    $trigger = $weekly[0]->getTrigger();
    self::assertInstanceOf(PeriodicalTrigger::class, $trigger);

    $nextRun = $trigger->getNextRunDate(new DateTimeImmutable('2026-09-02T12:00:00+00:00'));
    self::assertSame('2026-09-07 06:00:00 Monday', $nextRun?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s l'));
  }

  #[Test]
  public function testDomainProofsAreRecheckedEveryDay(): void
  {
    $messages = $this->createProvider()->getSchedule()->getRecurringMessages();
    $daily = array_values(array_filter($messages, static fn (RecurringMessage $message): bool => str_contains(self::scheduledCommandName($message), \Organization\Application\UseCase\Command\Sweep\VerifyOrganizationDomains\VerifyOrganizationDomainsCommand::class)));
    self::assertCount(1, $daily);
    $next = $daily[0]->getTrigger()->getNextRunDate(new DateTimeImmutable('2026-09-02T12:00:00+00:00'));
    self::assertSame('2026-09-03 12:00:00', $next?->format('Y-m-d H:i:s'));
  }

  #[Test]
  public function testScheduleIsStatefulAndLockGuarded(): void
  {
    $schedule = $this->createProvider()->getSchedule();

    self::assertNotNull($schedule->getState(), 'The schedule must be stateful to survive a restart.');
    self::assertNotNull($schedule->getLock(), 'The schedule must be locked against concurrent sweeps.');
  }

  /**
   * Return the command description after checking the scheduled provider type.
   *
   * @since 1.0.0
   *
   * @param RecurringMessage $message the registered recurring command
   *
   * @return string the static command description
   */
  private static function scheduledCommandName(RecurringMessage $message): string
  {
    $provider = $message->getProvider();
    self::assertInstanceOf(StaticMessageProvider::class, $provider);

    return (string) $provider;
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
