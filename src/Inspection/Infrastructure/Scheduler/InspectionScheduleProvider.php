<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Scheduler;

use Inspection\Application\UseCase\Command\Sweep\EscalateNonConformitySlaBreaches\EscalateNonConformitySlaBreachesCommand;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\{RecurringMessage, Schedule, ScheduleProviderInterface};
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Scheduler InspectionScheduleProvider.
 *
 * Triggers the non-conformity SLA escalation sweep hourly. The schedule is
 * stateful (missed triggers survive a restart, tracked in the cache pool)
 * and lock-guarded so overlapping deployments/workers never run the sweep
 * concurrently — mirrors `MaintenanceScheduleProvider`.
 *
 * The sweep message (`EscalateNonConformitySlaBreachesCommand`) is consumed
 * from the `scheduler_inspection` transport that the Scheduler component
 * registers automatically for this provider (DSN `schedule://inspection`);
 * run `messenger:consume scheduler_inspection` alongside the existing
 * `async` worker.
 *
 * @category Scheduler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsSchedule('inspection')]
final readonly class InspectionScheduleProvider implements ScheduleProviderInterface
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
      ->add(RecurringMessage::every('1 hour', new EscalateNonConformitySlaBreachesCommand()))
      ->stateful($this->cache)
      ->lock($this->lockFactory->createLock('inspection.nc_sla_sweep'));
  }
  // #endregion
}
