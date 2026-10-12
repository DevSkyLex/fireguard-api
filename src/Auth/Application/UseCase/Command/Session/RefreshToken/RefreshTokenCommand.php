<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Session\RefreshToken;

use Shared\Application\Message\CommandMessage;

/**
 * Class RefreshTokenCommand
 *
 * Requests the owner-conditional rotation of the presented interactive session pair.
 *
 * @category Command
 */
final readonly class RefreshTokenCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $refreshToken authenticated encrypted parent or receipt-bound daughter credential
   * @param ?string $ipAddress current request address
   *
   * @return void
   */
  public function __construct(public string $refreshToken, public ?string $ipAddress = null)
  {
  }
  // #endregion
}
