<?php

declare(strict_types=1);

namespace Compliance\Application\Port\Outbound;

use Compliance\Domain\Model\Snapshot\SafetyRegisterSnapshot;
use Compliance\Domain\ValueObject\SafetyRegisterSnapshotId;

/**
 * Port SafetyRegisterSnapshotRepositoryPort.
 *
 * Persistence port for archived safety register snapshots (main database,
 * `compliance_register_snapshots`). Every read is organization-scoped by
 * construction: a snapshot can never be fetched without naming the
 * organization it must belong to, which is what turns a cross-tenant lookup
 * into a plain "not found".
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface SafetyRegisterSnapshotRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a new snapshot. Snapshots are append-only: an existing row is
   * never updated.
   *
   * @since 1.0.0
   *
   * @param SafetyRegisterSnapshot $snapshot the snapshot to persist
   *
   * @return void no return value
   */
  public function save(SafetyRegisterSnapshot $snapshot): void;

  /**
   * Method findForOrganization.
   *
   * Finds a snapshot by identifier within one organization. Another
   * organization's snapshot answers null, exactly like an unknown id.
   *
   * @since 1.0.0
   *
   * @param SafetyRegisterSnapshotId $id the snapshot identifier
   * @param string $organizationId the organization the snapshot must belong to
   *
   * @return ?SafetyRegisterSnapshot the snapshot, or null when unknown or out of scope
   */
  public function findForOrganization(SafetyRegisterSnapshotId $id, string $organizationId): ?SafetyRegisterSnapshot;

  /**
   * Method listByOrganization.
   *
   * Lists an organization's snapshots, most recently generated first.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param int $limit the page size
   * @param int $offset the page offset
   *
   * @return list<SafetyRegisterSnapshot> the page of snapshots
   */
  public function listByOrganization(string $organizationId, int $limit, int $offset): array;

  /**
   * Method countByOrganization.
   *
   * Counts an organization's snapshots.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return int the snapshot count
   */
  public function countByOrganization(string $organizationId): int;
  // #endregion
}
