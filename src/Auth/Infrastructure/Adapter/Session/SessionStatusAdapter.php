<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Session;

use Auth\Application\Port\Outbound\SessionStatusPort;
use Session\Application\Port\Inbound\Tracking\SessionStatusPort as SessionStatusServicePort;

/**
 * Adapter SessionStatusAdapter.
 *
 * Bridges the Auth authenticator's revocation question to the Session module.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SessionStatusAdapter implements SessionStatusPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param SessionStatusServicePort $sessionStatus the session status service
   */
  public function __construct(
    private SessionStatusServicePort $sessionStatus,
  ) {
  }
  // #endregion

  // #region Methods
  public function isAccessTokenRevoked(string $accessTokenId): bool
  {
    return $this->sessionStatus->isAccessTokenRevoked(accessTokenId: $accessTokenId);
  }
  // #endregion
}
