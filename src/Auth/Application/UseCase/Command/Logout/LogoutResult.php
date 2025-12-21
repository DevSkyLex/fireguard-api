<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Logout;

use Shared\Application\Message\ResultMessage;

/**
 * Result LogoutResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LogoutResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the result with the logout status
   * and token revocation status.
   *
   * @since 1.0.0
   *
   * @param bool $success             whether the logout was successful
   * @param bool $refreshTokenRevoked whether the refresh token was revoked
   * @param bool $accessTokenRevoked  whether the access token was revoked
   */
  public function __construct(
    public readonly bool $success,
    public readonly bool $refreshTokenRevoked = false,
    public readonly bool $accessTokenRevoked = false,
  ) {
  }
  // #endregion
}
