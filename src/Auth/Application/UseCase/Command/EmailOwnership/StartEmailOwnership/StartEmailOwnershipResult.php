<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\EmailOwnership\StartEmailOwnership;

use Shared\Application\Message\ResultMessage;

/**
 * Result StartEmailOwnershipResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartEmailOwnershipResult implements ResultMessage
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param string $challengeToken generated challenge token
   * @param int $canResendIn seconds until another start is permitted
   */
  public function __construct(public string $challengeToken, public int $canResendIn)
  {
  }
  // #endregion
}
