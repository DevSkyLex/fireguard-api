<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\PurgeSessionLocations;

use Shared\Application\Message\ResultMessage;

/**
 * Count-only result without personal data.
 *
 * @category Result
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PurgeSessionLocationsResult implements ResultMessage
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param int $updated number of rows whose location keys were removed
   */
  public function __construct(public int $updated)
  {
  }
  // #endregion
}
