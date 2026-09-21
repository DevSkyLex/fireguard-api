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
   * @param list<ImportKind>|null $allowedKinds authorization scope, applied before pagination; empty grants no visibility
   *
   * @return list<ImportJob> the matching import jobs, most recent first
   */
  public function listByOrganization(string $organizationId, ?ImportKind $kind, int $limit, int $offset, ?array $allowedKinds = null): array;

  /**
   * Method countByOrganization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param ?ImportKind $kind optional resource kind filter
   * @param list<ImportKind>|null $allowedKinds the same authorization scope as the collection
   *
   * @return int the matching import job count
   */
  public function countByOrganization(string $organizationId, ?ImportKind $kind, ?array $allowedKinds = null): int;

  // #endregion
}
