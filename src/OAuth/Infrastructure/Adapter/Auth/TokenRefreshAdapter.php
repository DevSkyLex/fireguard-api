<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Auth;

use Auth\Application\Port\Outbound\TokenRefreshPort;
use Auth\Application\UseCase\Query\Session\RefreshToken\RefreshTokenResult as AuthRefreshTokenResult;
use OAuth\Application\UseCase\Query\Token\RefreshToken\{RefreshTokenQuery, RefreshTokenResult as OAuthRefreshTokenResult};
use Shared\Application\Port\Inbound\QueryBusPort;
use Throwable;

/**
 * Adapter TokenRefreshAdapter.
 *
 * Bridges Auth refresh requests to OAuth refresh logic.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenRefreshAdapter implements TokenRefreshPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  public function refresh(string $refreshToken, ?string $ipAddress = null): AuthRefreshTokenResult
  {
    try {
      /** @var OAuthRefreshTokenResult $result */
      $result = $this->queryBus->ask(new RefreshTokenQuery(
        refreshToken: $refreshToken,
        ipAddress: $ipAddress,
      ));
    } catch (Throwable $exception) {
      return AuthRefreshTokenResult::failed($exception->getMessage());
    }

    if (!$result->success) {
      return AuthRefreshTokenResult::failed($result->errorMessage ?? 'Invalid refresh token');
    }

    return new AuthRefreshTokenResult(
      success: true,
      userId: $result->userId,
      accessToken: $result->accessToken,
      refreshToken: $result->refreshToken,
      tokenType: $result->tokenType,
      expiresIn: $result->expiresIn,
      scopes: $result->scopes,
      accessTokenId: $result->accessTokenId,
      refreshTokenId: $result->refreshTokenId,
      rememberMe: $result->rememberMe,
    );
  }
  // #endregion
}
