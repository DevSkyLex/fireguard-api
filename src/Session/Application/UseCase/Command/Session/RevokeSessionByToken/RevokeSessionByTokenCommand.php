<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\RevokeSessionByToken;

use Shared\Application\Message\CommandMessage;

/**
 * Command RevokeSessionByTokenCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeSessionByTokenCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string|null $refreshTokenId the refresh token ID
   * @param string|null $accessTokenId the access token ID
   */
  public function __construct(
    public ?string $refreshTokenId,
    public ?string $accessTokenId,
  ) {
  }
  // #endregion
}
