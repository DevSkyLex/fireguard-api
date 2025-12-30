<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\Token\RevokeToken;

use Shared\Application\Message\ResultMessage;

/**
 * Result RevokeTokenResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeTokenResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @param bool $revoked whether the token was revoked
   */
  public function __construct(
    public bool $revoked,
  ) {
  }
}
