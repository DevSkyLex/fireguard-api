<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Sweep\SendWeeklyDigests;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase SendWeeklyDigestsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SendWeeklyDigestsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $organizationsScanned the number of active organizations scanned
   * @param int $digestsSent the number of digest emails sent across all organizations
   */
  public function __construct(
    public int $organizationsScanned,
    public int $digestsSent,
  ) {
  }
  // #endregion
}
