<?php

declare(strict_types=1);

namespace Session\Application\Service;

use Session\Application\Port\Inbound\Tracking\SessionStatusPort;
use Session\Application\UseCase\Query\Session\GetSessionByAccessToken\{GetSessionByAccessTokenHandler, GetSessionByAccessTokenQuery};

/**
 * Service SessionStatusService.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SessionStatusService implements SessionStatusPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param GetSessionByAccessTokenHandler $getSessionByAccessTokenHandler the session lookup handler
   */
  public function __construct(
    private GetSessionByAccessTokenHandler $getSessionByAccessTokenHandler,
  ) {
  }
  // #endregion

  // #region Methods
  public function isAccessTokenRevoked(string $accessTokenId): bool
  {
    if ('' === $accessTokenId) {
      return false;
    }

    $result = $this->getSessionByAccessTokenHandler->__invoke(
      new GetSessionByAccessTokenQuery(accessTokenId: $accessTokenId),
    );

    return $result->tracked && $result->revoked;
  }
  // #endregion
}
