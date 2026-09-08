<?php

declare(strict_types=1);

namespace Compliance\Application\Contract;

/**
 * Contract SafetyRegisterSnapshotView.
 *
 * Read-model row for an archived safety register snapshot — the metadata
 * only, never the PDF bytes. Named distinctly from the use case Results so
 * the two never blur at a call site.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SafetyRegisterSnapshotView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the snapshot identifier
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId the facility identifier, or null for an organization-wide register
   * @param string $scope `organization` or `facility`
   * @param string $generatedAt ISO 8601 datetime the register was generated at
   * @param string $generatedByUserId the user who requested the snapshot
   * @param string $contentHash the SHA-256 hash of the archived PDF bytes
   * @param int $sizeBytes the archived PDF size in bytes
   * @param string $createdAt ISO 8601 persistence datetime
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public ?string $facilityId,
    public string $scope,
    public string $generatedAt,
    public string $generatedByUserId,
    public string $contentHash,
    public int $sizeBytes,
    public string $createdAt,
  ) {
  }
  // #endregion
}
