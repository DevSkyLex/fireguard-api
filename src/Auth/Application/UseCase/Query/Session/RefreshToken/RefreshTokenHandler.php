<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\Session\RefreshToken;

use Auth\Application\Port\Outbound\TokenRefreshPort;
use Shared\Application\Message\QueryHandler;

/** The issuer must consume the previous session pair before returning tokens. */
final readonly class RefreshTokenHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the RefreshTokenHandler dependencies and state.
   *
   * @access public
   *
   * @param TokenRefreshPort $tokenRefresh the token refresh
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
   * Executes the use case represented by RefreshTokenHandler and returns its result.
   *
   * @access public
   *
   * @param RefreshTokenQuery $query the query to execute
   *
   * @return RefreshTokenResult
   */
  public function __invoke(RefreshTokenQuery $query): RefreshTokenResult
  {
    $tokens = $this->tokenRefresh->refresh($query->refreshToken, $query->ipAddress);

    return new RefreshTokenResult(
      success: $tokens->success,
      userId: $tokens->userId,
      accessToken: $tokens->accessToken,
      refreshToken: $tokens->refreshToken,
      tokenType: $tokens->tokenType,
      expiresIn: $tokens->expiresIn,
      scopes: $tokens->scopes,
      errorMessage: $tokens->errorMessage,
      accessTokenId: $tokens->accessTokenId,
      refreshTokenId: $tokens->refreshTokenId,
      rememberMe: $tokens->rememberMe,
    );
  }
  // #endregion
}
