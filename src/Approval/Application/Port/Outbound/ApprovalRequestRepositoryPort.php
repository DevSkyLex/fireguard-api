<?php

declare(strict_types=1);

namespace Approval\Application\Port\Outbound;

use Approval\Application\Contract\Reservation\ApprovalReservation;
use Approval\Domain\Model\ApprovalRequest\{ApprovalRequest, ApprovalRequestCreation};
use Approval\Domain\ValueObject\ApprovalRequestId;
use DateTimeImmutable;

/**
 * Port ApprovalRequestRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ApprovalRequestRepositoryPort
{
  // #region Methods
  /**
   * Method reservePending.
   *
   * Idempotently claims a `(organization, action type, subject)` tuple by
   * inserting a new pending row through a raw DBAL statement (never the
   * ORM's persist()/flush(): a unique-constraint violation during flush()
   * closes the EntityManager — mirrors
   * `Automation\Infrastructure\Persistence\Doctrine\Repository\AutomationRunRepository::reserveRun()`).
   * A duplicate claim (the partial unique index on `status = 'pending'`) is
   * an expected, routine outcome — not an exceptional one — and resolves to
   * the identifier of the ALREADY pending request.
   *
   * @since 1.0.0
   *
   * @param ApprovalRequestCreation $creation the pending request to claim
   *
   * @return ApprovalReservation the reservation outcome
   */
  public function reservePending(ApprovalRequestCreation $creation): ApprovalReservation;

  /**
   * Method save.
   *
   * Persists an approval request aggregate.
   *
   * @since 1.0.0
   *
   * @param ApprovalRequest $request the approval request aggregate
   */
  public function save(ApprovalRequest $request): void;

  /**
   * Method findById.
   *
   * @since 1.0.0
   *
   * @param ApprovalRequestId $id the approval request identifier
   *
   * @return ?ApprovalRequest the approval request when found
   */
  public function findById(ApprovalRequestId $id): ?ApprovalRequest;

  /**
   * Method listByOrganization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param ?string $status optional status filter
   * @param ?string $actionType optional action type filter
   * @param int $limit maximum number of results
   * @param int $offset result offset
   *
   * @return list<ApprovalRequest> the matching approval requests
   */
  public function listByOrganization(string $organizationId, ?string $status, ?string $actionType, int $limit, int $offset): array;

  /**
   * Method countByOrganization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param ?string $status optional status filter
   * @param ?string $actionType optional action type filter
   *
   * @return int the matching approval request count
   */
  public function countByOrganization(string $organizationId, ?string $status, ?string $actionType): int;

  /**
   * Method findPendingExpiredBefore.
   *
   * Pages through pending requests whose `expiresAt` is at or before the
   * given instant, using the `(status, expires_at)` index — feeds the
   * expiry sweep.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current time
   * @param int $limit maximum number of results
   *
   * @return list<ApprovalRequest> the stale pending approval requests
   */
  public function findPendingExpiredBefore(DateTimeImmutable $now, int $limit): array;
  // #endregion
}
