<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\Session\GetSessionByAccessToken;

use Session\Application\Port\Outbound\SessionRepositoryPort;
use Shared\Application\Message\QueryHandler;

/**
 * Handler GetSessionByAccessTokenHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetSessionByAccessTokenHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param SessionRepositoryPort $sessionRepository the session repository
   */
  public function __construct(
    private SessionRepositoryPort $sessionRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Resolves the session a given access token belongs to. An unknown token is
   * reported as untracked rather than as an error: this query answers an
   * authentication path that runs on every request, and a missing session row
   * is an expected outcome there, not an exceptional one.
   *
   * @since 1.0.0
   *
   * @param GetSessionByAccessTokenQuery $query the query to handle
   *
   * @return GetSessionByAccessTokenResult the result
   */
  public function __invoke(GetSessionByAccessTokenQuery $query): GetSessionByAccessTokenResult
  {
    if ('' === $query->accessTokenId) {
      return new GetSessionByAccessTokenResult(tracked: false, revoked: false);
    }

    $session = $this->sessionRepository->findByAccessTokenId(accessTokenId: $query->accessTokenId);

    if (null === $session) {
      return new GetSessionByAccessTokenResult(tracked: false, revoked: false);
    }

    return new GetSessionByAccessTokenResult(
      tracked: true,
      revoked: $session->isRevoked(),
      sessionId: (string) $session->id(),
      userId: $session->userId(),
    );
  }
  // #endregion
}
