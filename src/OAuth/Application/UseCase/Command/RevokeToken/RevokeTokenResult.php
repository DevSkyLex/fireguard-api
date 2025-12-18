<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\RevokeToken;

use Shared\Application\Message\ResultMessage;

/**
 * Result RevokeTokenResult
 * @final
 *
 * Result of the RevokeTokenCommand.
 *
 * @category Result
 * @package OAuth\Application\UseCase\Command\RevokeToken
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeTokenResult implements ResultMessage
{
  /**
   * Constructor
   *
   * @param bool $revoked Whether the token was revoked.
   */
  public function __construct(
    public bool $revoked,
  ) {}
}
