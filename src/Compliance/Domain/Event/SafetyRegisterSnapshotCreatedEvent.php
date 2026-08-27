<?php

declare(strict_types=1);

namespace Compliance\Domain\Event;

use DateTimeImmutable;

/**
 * Event SafetyRegisterSnapshotCreatedEvent.
 *
 * Raised each time a regulatory "registre de sécurité" PDF is archived as a
 * dated snapshot (organization-wide or facility-scoped). Recorded in the
 * audit ledger as `compliance.register_snapshot_created` (actor = requesting
 * user, subject = the snapshot, payload = scope + plan + generatedAt +
 * content hash + size), so the append-only archive and the tamper-evident
 * ledger corroborate each other.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SafetyRegisterSnapshotCreatedEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the SafetyRegisterSnapshotCreatedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $snapshotId the snapshot identifier
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId the facility identifier, or null for an organization-wide snapshot
   * @param string $actorUserId the requesting user identifier
   * @param string $planKey the organization's plan key at snapshot time
   * @param string $scope `organization` or `facility`
   * @param string $generatedAt ISO 8601 datetime the archived register was generated at
   * @param string $contentHash the SHA-256 hash of the archived PDF bytes
   * @param int $sizeBytes the archived PDF size in bytes
   */
  public function __construct(
    public string $snapshotId,
    public string $organizationId,
    public ?string $facilityId,
    public string $actorUserId,
    public string $planKey,
    public string $scope,
    public string $generatedAt,
    public string $contentHash,
    public int $sizeBytes,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
