<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Organization\Application\UseCase\Command\Sweep\SendWeeklyDigests\SendWeeklyDigestsCommand;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\{RecurringMessage, Schedule, ScheduleProviderInterface};
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Scheduler OrganizationScheduleProvider.
 *
 * Triggers the weekly organization digest sweep every Monday at 06:00 UTC —
 * early enough to land in European inboxes before the working week starts,
 * late enough that the Sunday-night hourly sweeps have refreshed the
 * maintenance due statuses and SLA stamps the digest reads. The weekly
 * cadence is an anchored 1-week periodical trigger (see
 * WEEKLY_DIGEST_ANCHOR), not a cron expression. The schedule is
 * stateful (missed triggers survive a restart, tracked in the cache pool)
 * and lock-guarded so overlapping deployments/workers never run the sweep
 * concurrently — mirrors `InspectionScheduleProvider`.
 *
 * The sweep message (`SendWeeklyDigestsCommand`) is consumed from the
 * `scheduler_organization` transport that the Scheduler component registers
 * automatically for this provider (DSN `schedule://organization`); run
 * `messenger:consume scheduler_organization` alongside the existing
 * `async` worker.
 *
 * @category Scheduler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsSchedule('organization')]
final readonly class OrganizationScheduleProvider implements ScheduleProviderInterface
{
  // #region Constants
  /**
   * Anchor of the weekly digest trigger: a Monday 06:00 UTC. The schedule
   * fires every 7 days from this instant — an anchored periodical trigger
   * rather than a cron expression, because the optional
   * `dragonmantank/cron-expression` package is deliberately not a
   * dependency of this app.
   */
  private const string WEEKLY_DIGEST_ANCHOR = '2026-08-31 06:00:00';
  // #endregion

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
      ->add(RecurringMessage::every(
        '1 week',
        new SendWeeklyDigestsCommand(),
        from: new DateTimeImmutable(self::WEEKLY_DIGEST_ANCHOR, new DateTimeZone('UTC')),
      ))
      ->stateful($this->cache)
      ->lock($this->lockFactory->createLock('organization.weekly_digest_sweep'));
  }
  // #endregion
}
