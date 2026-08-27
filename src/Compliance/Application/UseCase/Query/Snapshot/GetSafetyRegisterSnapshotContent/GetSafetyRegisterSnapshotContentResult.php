<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Query\Snapshot\GetSafetyRegisterSnapshotContent;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetSafetyRegisterSnapshotContentResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetSafetyRegisterSnapshotContentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $contents the archived PDF bytes
   * @param string $snapshotId the snapshot identifier
   * @param ?string $facilityId the facility identifier, or null for an organization-wide register
   * @param string $generatedAt ISO 8601 datetime the archived register was generated at
   * @param string $contentHash the SHA-256 hash of the archived PDF bytes
   * @param int $sizeBytes the archived PDF size in bytes
   */
  public function __construct(
    public string $contents,
    public string $snapshotId,
    public ?string $facilityId,
    public string $generatedAt,
    public string $contentHash,
    public int $sizeBytes,
  ) {
  }
  // #endregion
}
