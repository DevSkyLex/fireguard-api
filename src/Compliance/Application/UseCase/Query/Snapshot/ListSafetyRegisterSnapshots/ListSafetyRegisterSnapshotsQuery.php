<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Query\Snapshot\ListSafetyRegisterSnapshots;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListSafetyRegisterSnapshotsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListSafetyRegisterSnapshotsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $userId the requesting user identifier
   * @param int $page the 1-based page number
   * @param int $itemsPerPage the page size
   */
  public function __construct(
    public string $organizationId,
    public string $userId,
    public int $page = 1,
    public int $itemsPerPage = 30,
  ) {
  }
  // #endregion
}
