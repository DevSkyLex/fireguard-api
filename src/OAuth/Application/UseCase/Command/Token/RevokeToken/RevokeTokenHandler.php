<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\Token\RevokeToken;

use OAuth\Application\Port\Outbound\Token\TokenRevocationPort;
use Shared\Application\Message\CommandHandler;

/**
 * Handler RevokeTokenHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeTokenHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RevokeTokenHandler class.
   *
   * @since 1.0.0
   *
   * @param TokenRevocationPort $tokenRevocation the token revocation service
   */
  public function __construct(
    private readonly TokenRevocationPort $tokenRevocation,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the RevokeTokenCommand.
   *
   * @since 1.0.0
   *
   * @param RevokeTokenCommand $command the command
   *
   * @return RevokeTokenResult the result
   */
  public function __invoke(RevokeTokenCommand $command): RevokeTokenResult
  {

    // Try based on hint first
    if (RevokeTokenCommand::HINT_REFRESH_TOKEN === $command->tokenTypeHint) {
      if ($this->tokenRevocation->revokeRefreshToken($command->token)) {
        return new RevokeTokenResult(revoked: true);
      }
    } elseif (RevokeTokenCommand::HINT_ACCESS_TOKEN === $command->tokenTypeHint) {
      if ($this->tokenRevocation->revokeAccessToken($command->token)) {
        return new RevokeTokenResult(revoked: true);
      }
    }

    // If hint didn't work or wasn't provided, try both
    $revoked = $this->tokenRevocation->revokeRefreshToken($command->token)
      || $this->tokenRevocation->revokeAccessToken($command->token);

    return new RevokeTokenResult(revoked: $revoked);
  }

  // #endregion
}
