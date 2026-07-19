<?php

declare(strict_types=1);

namespace Approval\Infrastructure\Scheduler;

use Approval\Application\UseCase\Command\Sweep\ExpireStaleApprovalRequests\ExpireStaleApprovalRequestsCommand;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\{RecurringMessage, Schedule, ScheduleProviderInterface};
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Scheduler ApprovalScheduleProvider.
 *
 * Triggers the approval-request expiry sweep hourly. The schedule is
 * stateful (missed triggers survive a restart, tracked in the cache pool)
 * and lock-guarded so overlapping deployments/workers never run the sweep
 * concurrently — a clone of `Maintenance\Infrastructure\Scheduler\MaintenanceScheduleProvider`.
 *
 * The sweep message (`ExpireStaleApprovalRequestsCommand`) is dispatched
 * directly onto the `scheduler_approval` transport that the Scheduler
 * component registers automatically for this provider (DSN
 * `schedule://approval`) — it never goes through `CommandBusPort` (which
 * requires a `HandledStamp` and would fail for an async-routed message).
 * Run `messenger:consume scheduler_approval` alongside the existing `async`
 * worker.
 *
 * @category Scheduler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsSchedule('approval')]
final readonly class ApprovalScheduleProvider implements ScheduleProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CacheInterface $cache the cache pool backing the stateful schedule
   * @param LockFactory $lockFactory the lock factory guarding concurrent runs
   */
  public function __construct(
    private CacheInterface $cache,
    private LockFactory $lockFactory,
  ) {
  }
  // #endregion

  // #region Methods
  public function getSchedule(): Schedule
  {
    return new Schedule()
      ->add(RecurringMessage::every('1 hour', new ExpireStaleApprovalRequestsCommand()))
      ->stateful($this->cache)
      ->lock($this->lockFactory->createLock('approval.expiry_sweep'));
  }
  // #endregion
}
