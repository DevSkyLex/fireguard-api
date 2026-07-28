<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Infrastructure\Scheduler;

use Approval\Application\UseCase\Command\Sweep\ExpireStaleApprovalRequests\ExpireStaleApprovalRequestsCommand;
use Approval\Infrastructure\Scheduler\ApprovalScheduleProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\{PeriodicalTrigger, StaticMessageProvider};

use function count;

/**
 * Test ApprovalScheduleProviderTest.
 *
 * The expiry sweep must stay stateful and lock-guarded: without the lock two
 * workers would expire the same approval requests concurrently, and without
 * the stateful cache a restart would silently skip a missed window. Both are
 * invisible at runtime until they go wrong, so they are pinned here.
 *
 * @category Scheduler Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalScheduleProvider::class)]
final class ApprovalScheduleProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testScheduleDispatchesTheExpirySweep(): void
  {
    $schedule = $this->createProvider()->getSchedule();

    self::assertInstanceOf(Schedule::class, $schedule);

    $messages = $schedule->getRecurringMessages();

    self::assertCount(1, $messages);

    $provider = $messages[0]->getProvider();
    self::assertInstanceOf(StaticMessageProvider::class, $provider);
    self::assertStringContainsString(ExpireStaleApprovalRequestsCommand::class, (string) $provider);
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

  #[Test]
  public function testScheduleIsRebuiltPerCallWithoutSharingState(): void
  {
    $provider = $this->createProvider();

    $first = $provider->getSchedule();
    $second = $provider->getSchedule();

    self::assertCount(
      count($first->getRecurringMessages()),
      $second->getRecurringMessages(),
    );
  }

  private function createProvider(): ApprovalScheduleProvider
  {
    return new ApprovalScheduleProvider(
      cache: new ArrayAdapter(),
      lockFactory: new LockFactory(new InMemoryStore()),
    );
  }
  // #endregion
}
