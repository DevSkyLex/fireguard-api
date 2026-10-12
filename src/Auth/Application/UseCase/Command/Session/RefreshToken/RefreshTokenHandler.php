<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Session\RefreshToken;

use Auth\Application\Port\Outbound\TokenRefreshPort;
use Shared\Application\Message\CommandHandler;

/**
 * Class RefreshTokenHandler
 *
 * Runs the issuer's conditional session-pair rotation on the command path.
 *
 * @category Handler
 */
final readonly class RefreshTokenHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param TokenRefreshPort $tokenRefresh existing owner rotation capability
   *
   * @return void
   */
  public function __construct(private TokenRefreshPort $tokenRefresh)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * @access public
   *
   * @param RefreshTokenCommand $command verified refresh attempt
   *
   * @return RefreshTokenResult immutable application outcome
   */
  public function __invoke(RefreshTokenCommand $command): RefreshTokenResult
  {
    $tokens = $this->tokenRefresh->refresh($command->refreshToken, $command->ipAddress);

    return new RefreshTokenResult($tokens->success, $tokens->userId, $tokens->accessToken, $tokens->refreshToken, $tokens->tokenType, $tokens->expiresIn, $tokens->scopes, $tokens->errorMessage, $tokens->accessTokenId, $tokens->refreshTokenId, $tokens->rememberMe);
  }
  // #endregion
}
