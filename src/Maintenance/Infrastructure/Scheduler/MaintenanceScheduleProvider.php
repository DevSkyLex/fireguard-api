<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Scheduler;

use Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules\RecomputeMaintenanceSchedulesCommand;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\{RecurringMessage, Schedule, ScheduleProviderInterface};
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Scheduler MaintenanceScheduleProvider.
 *
 * Triggers the maintenance schedule sweep hourly. The schedule is stateful
 * (missed triggers survive a restart, tracked in the cache pool) and
 * lock-guarded so overlapping deployments/workers never run the sweep
 * concurrently.
 *
 * The sweep message (`RecomputeMaintenanceSchedulesCommand`) is consumed
 * from the `scheduler_maintenance` transport that the Scheduler component
 * registers automatically for this provider (DSN `schedule://maintenance`);
 * run `messenger:consume scheduler_maintenance` alongside the existing
 * `async` worker.
 *
 * @category Scheduler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsSchedule('maintenance')]
final readonly class MaintenanceScheduleProvider implements ScheduleProviderInterface
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
      ->add(RecurringMessage::every('1 hour', new RecomputeMaintenanceSchedulesCommand()))
      ->stateful($this->cache)
      ->lock($this->lockFactory->createLock('maintenance.schedule_sweep'));
  }
  // #endregion
}
