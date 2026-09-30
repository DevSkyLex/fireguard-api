<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Session;

use OAuth\Application\Port\Outbound\SessionRotationPort;
use Session\Application\Port\Inbound\Tracking\SessionTrackingPort;

/**
 * Class SessionRotationAdapter
 *
 * Bridges OAuth refresh-token rotation to the Session module's tracking port.
 *
 * @category Adapter
 */
final readonly class SessionRotationAdapter implements SessionRotationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives Session's inbound tracking port as the implementation path for OAuth refresh-session rotation.
   *
   * @access public
   *
   * @param SessionTrackingPort $sessions performs atomic session token rotation
   *
   * @return void
   */
  public function __construct(private SessionTrackingPort $sessions)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method rotate
   *
   * Delegates rotation and reports whether the session tokens were updated.
   *
   * @access public
   *
   * @param string $refreshTokenId the refresh token identifier being rotated
   * @param string $accessTokenId the current access token identifier
   * @param string $newAccessTokenId the replacement access token identifier
   * @param string $newRefreshTokenId the replacement refresh token identifier
   *
   * @return bool whether rotation succeeded
   */
  public function rotate(string $refreshTokenId, string $accessTokenId, string $newAccessTokenId, string $newRefreshTokenId): bool
  {
    return $this->sessions->rotateSessionTokens($refreshTokenId, $accessTokenId, $newAccessTokenId, $newRefreshTokenId);
  }
  // #endregion
}
