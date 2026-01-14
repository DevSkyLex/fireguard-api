<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\Session\RefreshToken;

use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionTrackingPort, TokenRefreshPort};
use Shared\Application\Message\QueryHandler;
use Throwable;

use function is_array;

/**
 * Handler RefreshTokenHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RefreshTokenHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param TokenRefreshPort $tokenRefresh the token refresh port
   * @param JwtTokenServicePort $jwtService the JWT service
   * @param SessionTrackingPort $sessionTracking the session tracking port
   */
  public function __construct(
    private TokenRefreshPort $tokenRefresh,
    private JwtTokenServicePort $jwtService,
    private SessionTrackingPort $sessionTracking,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the RefreshTokenQuery.
   *
   * @param RefreshTokenQuery $query the query
   *
   * @return RefreshTokenResult the result
   */
  public function __invoke(RefreshTokenQuery $query): RefreshTokenResult
  {
    $currentPayload = $this->jwtService->decodeRefreshToken($query->refreshToken);
    $currentRefreshTokenId = null;
    $currentAccessTokenId = null;
    if (is_array($currentPayload)) {
      $currentRefreshTokenId = $currentPayload['refresh_token_id'];
      $currentAccessTokenId = $currentPayload['access_token_id'];
    }

    $result = $this->tokenRefresh->refresh(
      refreshToken: $query->refreshToken,
      ipAddress: $query->ipAddress,
    );

    $this->rotateSessionTokens(
      $currentRefreshTokenId,
      $currentAccessTokenId,
      $result,
    );

    return $result;
  }

  /**
   * Method rotateSessionTokens.
   *
   * Updates session tokens after a refresh.
   *
   * @param string|null $currentRefreshTokenId the current refresh token ID
   * @param string|null $currentAccessTokenId the current access token ID
   * @param RefreshTokenResult $result the refresh result
   *
   * @return void no return value
   */
  private function rotateSessionTokens(
    ?string $currentRefreshTokenId,
    ?string $currentAccessTokenId,
    RefreshTokenResult $result,
  ): void {
    if (
      null === $currentRefreshTokenId
      || '' === $currentRefreshTokenId
      || !$result->success
      || null === $result->refreshToken
    ) {
      return;
    }

    $newPayload = $this->jwtService->decodeRefreshToken($result->refreshToken);
    if (!is_array($newPayload)) {
      return;
    }

    $newAccessTokenId = $newPayload['access_token_id'];
    $newRefreshTokenId = $newPayload['refresh_token_id'];

    if ('' === $newAccessTokenId || '' === $newRefreshTokenId) {
      return;
    }

    try {
      $this->sessionTracking->rotateSessionTokens(
        currentRefreshTokenId: $currentRefreshTokenId,
        currentAccessTokenId: $currentAccessTokenId,
        newAccessTokenId: $newAccessTokenId,
        newRefreshTokenId: $newRefreshTokenId,
      );
    } catch (Throwable) {
      // Best-effort session tracking; refresh must not fail.
    }
  }
  // #endregion
}
