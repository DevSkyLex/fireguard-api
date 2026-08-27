<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Query\Snapshot\GetSafetyRegisterSnapshotContent;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetSafetyRegisterSnapshotContentQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetSafetyRegisterSnapshotContentQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $snapshotId the snapshot identifier
   * @param string $userId the requesting user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $snapshotId,
    public string $userId,
  ) {
  }
  // #endregion
}
