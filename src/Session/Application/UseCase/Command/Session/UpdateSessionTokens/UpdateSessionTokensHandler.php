<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\UpdateSessionTokens;

use Session\Application\Port\Outbound\SessionRepositoryPort;
use Shared\Application\Message\CommandHandler;

use function trim;

/**
 * Handler UpdateSessionTokensHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateSessionTokensHandler implements CommandHandler
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
   * Handles the UpdateSessionTokensCommand.
   *
   * @since 1.0.0
   *
   * @param UpdateSessionTokensCommand $command the command
   *
   * @return UpdateSessionTokensResult the result
   */
  public function __invoke(UpdateSessionTokensCommand $command): UpdateSessionTokensResult
  {
    $refreshTokenId = trim($command->currentRefreshTokenId);
    $accessTokenId = trim($command->currentAccessTokenId ?? '');
    if ('' === $refreshTokenId || '' === $accessTokenId || '' === $command->newAccessTokenId || '' === $command->newRefreshTokenId) {
      return new UpdateSessionTokensResult(updated: false);
    }

    return new UpdateSessionTokensResult(updated: $this->sessionRepository->rotateTokens(
      $refreshTokenId,
      $accessTokenId,
      $command->newAccessTokenId,
      $command->newRefreshTokenId,
    ));
  }
  // #endregion
}
