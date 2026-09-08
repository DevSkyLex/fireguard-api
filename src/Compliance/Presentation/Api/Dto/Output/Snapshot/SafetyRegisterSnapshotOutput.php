<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Dto\Output\Snapshot;

/**
 * Dto SafetyRegisterSnapshotOutput.
 *
 * Archived safety register snapshot metadata — the PDF bytes are served by
 * the dedicated `…/download` operation, never inlined here.
 *
 * @category Dto
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SafetyRegisterSnapshotOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  public string $id = '';

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  public string $organizationId = '';

  /**
   * Property facilityId.
   *
   * Null for an organization-wide register snapshot.
   *
   * @since 1.0.0
   */
  public ?string $facilityId = null;

  /**
   * Property scope.
   *
   * `organization` or `facility`.
   *
   * @since 1.0.0
   */
  public string $scope = '';

  /**
   * Property generatedAt.
   *
   * ISO 8601 datetime the archived register was generated at.
   *
   * @since 1.0.0
   */
  public string $generatedAt = '';

  /**
   * Property generatedByUserId.
   *
   * @since 1.0.0
   */
  public string $generatedByUserId = '';

  /**
   * Property contentHash.
   *
   * SHA-256 hex digest of the archived PDF bytes.
   *
   * @since 1.0.0
   */
  public string $contentHash = '';

  /**
   * Property sizeBytes.
   *
   * @since 1.0.0
   */
  public int $sizeBytes = 0;

  /**
   * Property createdAt.
   *
   * ISO 8601 datetime the snapshot row was persisted at.
   *
   * @since 1.0.0
   */
  public string $createdAt = '';
  // #endregion
}
