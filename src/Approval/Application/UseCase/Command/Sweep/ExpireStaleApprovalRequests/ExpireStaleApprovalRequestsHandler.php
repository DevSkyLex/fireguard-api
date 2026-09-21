<?php

declare(strict_types=1);

namespace Approval\Application\UseCase\Command\Sweep\ExpireStaleApprovalRequests;

use Approval\Application\Port\Outbound\{ApprovalDecisionLockPort, ApprovalRequestRepositoryPort};
use Approval\Domain\Event\Request\ApprovalExpiredEvent;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

use function count;

/**
 * UseCase ExpireStaleApprovalRequestsHandler.
 *
 * Idempotent, safe-to-re-run recurring sweep: pages through pending requests
 * whose `expiresAt` deadline has passed (the `(status, expires_at)` index),
 * transitions each to `expired`, and dispatches {@see ApprovalExpiredEvent}.
 * Mirrors `Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules\RecomputeMaintenanceSchedulesHandler`.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExpireStaleApprovalRequestsHandler implements CommandHandler
{
  // #region Constants
  /**
   * Page size, keeping every batch bounded in memory.
   */
  private const int PAGE_SIZE = 200;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ApprovalRequestRepositoryPort $requests the approval request repository port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param ClockPort $clock the clock port
   * @param ApprovalDecisionLockPort $decisions the transactional decision lock
   */
  public function __construct(
    private ApprovalRequestRepositoryPort $requests,
    private EventDispatcherPort $eventDispatcher,
    private ClockPort $clock,
    private ApprovalDecisionLockPort $decisions,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ExpireStaleApprovalRequestsCommand $command the command value
   *
   * @return VoidResult the command result
   */
  public function __invoke(ExpireStaleApprovalRequestsCommand $command): VoidResult
  {
    $now = $this->clock->now();

    do {
      $page = $this->requests->findPendingExpiredBefore($now, self::PAGE_SIZE);

      foreach ($page as $request) {
        $this->decisions->synchronized((string) $request->id(), function () use ($request): void {
          $current = $this->requests->findById($request->id());
          $now = $this->clock->now();
          if (null === $current || !$current->isPending() || $current->expiresAt() > $now) {
            return;
          }

          $current->expire($now);
          $this->requests->save($current);

          $this->eventDispatcher->dispatch(new ApprovalExpiredEvent(
            organizationId: $current->organizationId(),
            requestId: (string) $current->id(),
            actionType: $current->actionType(),
            subjectId: $current->subjectId(),
          ));
        });
      }
    } while (self::PAGE_SIZE === count($page));

    return new VoidResult();
  }
  // #endregion
}
