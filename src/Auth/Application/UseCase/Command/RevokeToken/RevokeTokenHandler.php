<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\RevokeToken;

use Auth\Application\Port\Outbound\TokenRevocationPort;
use Shared\Application\Message\CommandHandler;

/**
 * Handler RevokeTokenHandler
 * @final
 *
 * Handles the RevokeTokenCommand (RFC 7009).
 *
 * @category Handler
 * @package Auth\Application\UseCase\Command\RevokeToken
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeTokenHandler implements CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * RevokeTokenHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TokenRevocationPort $tokenRevocation The token revocation service.
   */
  public function __construct(
    private readonly TokenRevocationPort $tokenRevocation,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the RevokeTokenCommand.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param RevokeTokenCommand $command The command.
   *
   * @return RevokeTokenResult The result.
   */
  public function __invoke(RevokeTokenCommand $command): RevokeTokenResult
  {

    // Try based on hint first
    if ($command->tokenTypeHint === RevokeTokenCommand::HINT_REFRESH_TOKEN) {
      if ($this->tokenRevocation->revokeRefreshToken($command->token)) {
        return new RevokeTokenResult(revoked: true);
      }
    } 
    elseif ($command->tokenTypeHint === RevokeTokenCommand::HINT_ACCESS_TOKEN) {
      if ($this->tokenRevocation->revokeAccessToken($command->token)) {
        return new RevokeTokenResult(revoked: true);
      }
    }

    // If hint didn't work or wasn't provided, try both
    $revoked = $this->tokenRevocation->revokeRefreshToken($command->token)
      || $this->tokenRevocation->revokeAccessToken($command->token);

    return new RevokeTokenResult(revoked: $revoked);
  }

  //#endregion
}
