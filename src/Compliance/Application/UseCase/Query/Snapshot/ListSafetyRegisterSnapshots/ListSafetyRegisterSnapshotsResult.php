<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Query\Snapshot\ListSafetyRegisterSnapshots;

use Compliance\Application\Contract\SafetyRegisterSnapshotView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListSafetyRegisterSnapshotsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListSafetyRegisterSnapshotsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<SafetyRegisterSnapshotView> $items the page items, most recently generated first
   * @param int $page the 1-based page number
   * @param int $itemsPerPage the page size
   * @param int $total the total snapshot count for the organization
   */
  public function __construct(
    public array $items,
    public int $page,
    public int $itemsPerPage,
    public int $total,
  ) {
  }
  // #endregion
}
