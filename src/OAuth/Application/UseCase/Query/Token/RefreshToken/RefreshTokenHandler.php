<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\Token\RefreshToken;

use Auth\Application\Port\Outbound\JwtTokenServicePort;
use OAuth\Domain\Event\Token\{TokenRefreshFailedEvent, TokenRefreshedEvent};
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Throwable;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function array_key_exists;
use function is_bool;
use function is_string;

/**
 * Handler RefreshTokenHandler.
 *
 * @category Handler
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RefreshTokenHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param JwtTokenServicePort $tokenService the JWT token service
   * @param QueryBusPort $queryBus the query bus
   * @param EventDispatcherPort $eventDispatcher the event dispatcher
   */
  public function __construct(
    private JwtTokenServicePort $tokenService,
    private QueryBusPort $queryBus,
    private EventDispatcherPort $eventDispatcher,
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
    if ('' === $query->refreshToken) {
      return RefreshTokenResult::failed('Refresh token is required');
    }

    $payload = $this->tokenService->decodeRefreshToken($query->refreshToken);

    if (null === $payload) {
      $this->eventDispatcher->dispatch(new TokenRefreshFailedEvent(
        userId: null,
        ipAddress: $query->ipAddress,
        reason: 'invalid_token',
      ));

      return RefreshTokenResult::failed('Invalid or expired refresh token');
    }

    $userId = $payload['user_id'];

    if ('' === $userId) {
      return RefreshTokenResult::failed('Invalid refresh token');
    }

    /** @var non-empty-string $userId */

    // Verify user is still active
    $userResult = $this->verifyUserActive($userId, $query->ipAddress);
    if (null !== $userResult) {
      return $userResult;
    }

    /** @var list<string> $scopes */
    $scopes = $payload['scopes'];
    $rememberMe = $this->resolveRememberMe($payload);

    $tokens = $this->tokenService->generateTokens(
      userId: $userId,
      email: '',
      scopes: $scopes,
      rememberMe: $rememberMe,
    );

    $this->eventDispatcher->dispatch(new TokenRefreshedEvent(
      userId: $userId,
      ipAddress: $query->ipAddress,
    ));

    return new RefreshTokenResult(
      success: true,
      userId: $userId,
      accessToken: $tokens['access_token'],
      refreshToken: $tokens['refresh_token'],
      tokenType: $tokens['token_type'],
      expiresIn: $tokens['expires_in'],
      scopes: $scopes,
      accessTokenId: $this->getTokenIdentifier($tokens, 'access_token_id'),
      refreshTokenId: $this->getTokenIdentifier($tokens, 'refresh_token_id'),
      rememberMe: $rememberMe,
    );
  }

  /**
   * Method verifyUserActive.
   *
   * Verifies that the user is still active.
   *
   * @param string $userId the user ID
   * @param string|null $ipAddress the IP address
   *
   * @return RefreshTokenResult|null error result if user is not active, null otherwise
   */
  private function verifyUserActive(string $userId, ?string $ipAddress): ?RefreshTokenResult
  {
    try {
      /** @var GetUserResult $userResult */
      $userResult = $this->queryBus->ask(new GetUserQuery(id: $userId));

      if (null === $userResult->user) {
        $this->eventDispatcher->dispatch(new TokenRefreshFailedEvent(
          userId: $userId,
          ipAddress: $ipAddress,
          reason: 'user_not_found',
        ));

        return RefreshTokenResult::failed('User not found');
      }

      if (!$userResult->user->canLogin) {
        $this->eventDispatcher->dispatch(new TokenRefreshFailedEvent(
          userId: $userId,
          ipAddress: $ipAddress,
          reason: 'user_inactive',
        ));

        return RefreshTokenResult::failed('User account is not active');
      }

      return null;
    } catch (Throwable) {
      $this->eventDispatcher->dispatch(new TokenRefreshFailedEvent(
        userId: $userId,
        ipAddress: $ipAddress,
        reason: 'verification_error',
      ));

      return RefreshTokenResult::failed('Failed to verify user');
    }
  }

  /**
   * @param array<string, mixed> $payload
   */
  private function resolveRememberMe(array $payload): bool
  {
    $rememberMe = $payload['remember_me'] ?? null;

    return is_bool($rememberMe) ? $rememberMe : false;
  }

  /**
   * @param array<string, mixed> $tokens issued tokens
   */
  private function getTokenIdentifier(array $tokens, string $key): ?string
  {
    if (!array_key_exists($key, $tokens) || !is_string($tokens[$key]) || '' === $tokens[$key]) {
      return null;
    }

    return $tokens[$key];
  }
  // #endregion
}
