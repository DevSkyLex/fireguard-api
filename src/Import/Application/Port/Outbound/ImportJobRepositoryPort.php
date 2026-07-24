<?php

declare(strict_types=1);

namespace Import\Application\Port\Outbound;

use Import\Domain\Model\ImportJob\ImportJob;
use Import\Domain\ValueObject\{ImportJobId, ImportKind};

/**
 * Port ImportJobRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ImportJobRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Creates or updates an import job from its current aggregate state.
   *
   * @since 1.0.0
   *
   * @param ImportJob $job the import job aggregate
   */
  public function save(ImportJob $job): void;

  /**
   * Method findById.
   *
   * @since 1.0.0
   *
   * @param ImportJobId $id the import job identifier
   *
   * @return ?ImportJob the import job, or null when not found
   */
  public function findById(ImportJobId $id): ?ImportJob;

  /**
   * Method listByOrganization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param ?ImportKind $kind optional resource kind filter
   * @param int $limit maximum number of results
   * @param int $offset result offset
   *
   * @return list<ImportJob> the matching import jobs, most recent first
   */
  public function listByOrganization(string $organizationId, ?ImportKind $kind, int $limit, int $offset): array;

  /**
   * Method countByOrganization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param ?ImportKind $kind optional resource kind filter
   *
   * @return int the matching import job count
   */
  public function countByOrganization(string $organizationId, ?ImportKind $kind): int;

  /**
   * Method claim.
   *
   * Idempotently claims a non-terminal job (`pending` or already
   * `processing`) via a raw-DBAL conditional UPDATE, mirroring
   * `AutomationRunRepository::reserveRun()` — a no-op UPDATE during an ORM
   * flush would otherwise close the EntityManager. Accepting an
   * already-`processing` job (not just `pending`) is deliberate: it lets a
   * Messenger redelivery after a worker crash resume the same job from its
   * persisted `processedRows` high-water mark instead of being rejected,
   * while a `completed`/`failed` (terminal) job is never reclaimed — the
   * idempotence guard that matters.
   *
   * @since 1.0.0
   *
   * @param ImportJobId $id the import job identifier
   *
   * @return bool true when this call claimed (or re-claimed) the job, false
   *              when it is already in a terminal state
   */
  public function claim(ImportJobId $id): bool;
  // #endregion
}
