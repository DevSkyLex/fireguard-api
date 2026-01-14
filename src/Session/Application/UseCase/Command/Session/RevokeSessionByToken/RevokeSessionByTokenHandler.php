<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\RevokeSessionByToken;

use Session\Application\Port\Outbound\SessionRepositoryPort;
use Shared\Application\Message\CommandHandler;

use function trim;

/**
 * Handler RevokeSessionByTokenHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeSessionByTokenHandler implements CommandHandler
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
   * Handles the RevokeSessionByTokenCommand.
   *
   * @since 1.0.0
   *
   * @param RevokeSessionByTokenCommand $command the command
   *
   * @return RevokeSessionByTokenResult the result
   */
  public function __invoke(RevokeSessionByTokenCommand $command): RevokeSessionByTokenResult
  {
    $refreshTokenId = $command->refreshTokenId ? trim($command->refreshTokenId) : '';
    $accessTokenId = $command->accessTokenId ? trim($command->accessTokenId) : '';

    $session = null;
    if ('' !== $refreshTokenId) {
      $session = $this->sessionRepository->findByRefreshTokenId($refreshTokenId);
    }

    if (null === $session && '' !== $accessTokenId) {
      $session = $this->sessionRepository->findByAccessTokenId($accessTokenId);
    }

    if (null === $session) {
      return new RevokeSessionByTokenResult(revoked: false, sessionId: null);
    }

    $session->revoke();
    $this->sessionRepository->save(session: $session);

    return new RevokeSessionByTokenResult(
      revoked: true,
      sessionId: (string) $session->id(),
    );
  }
  // #endregion
}
