<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\RefreshToken;

use Auth\Application\Port\Inbound\RefreshTokenUseCasePort;
use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;
use User\Application\UseCase\Query\GetUser\GetUserQuery;
use User\Application\UseCase\Query\GetUser\GetUserResult;

/**
 * Handler RefreshTokenHandler
 * @final
 *
 * Handles token refresh using a refresh token.
 * Validates the user is still active before issuing new tokens.
 *
 * @category Handler
 * @package Auth\Application\UseCase\Query\RefreshToken
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RefreshTokenHandler implements RefreshTokenUseCasePort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * RefreshTokenHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param JwtTokenServicePort $tokenService The JWT token service.
   * @param QueryBusPort $queryBus The query bus.
   * @param LoggerInterface $logger The security logger.
   */
  public function __construct(
    private JwtTokenServicePort $tokenService,
    private QueryBusPort $queryBus,
    #[Autowire(service: 'monolog.logger.security')]
    private LoggerInterface $logger,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the RefreshTokenQuery.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RefreshTokenQuery $query The query.
   *
   * @return RefreshTokenResult The result.
   */
  public function __invoke(RefreshTokenQuery $query): RefreshTokenResult
  {
    if ($query->refreshToken === '') {
      return RefreshTokenResult::failed('Refresh token is required');
    }

    $payload = $this->tokenService->decodeRefreshToken($query->refreshToken);

    if ($payload === null) {
      $this->logger->warning('Invalid refresh token attempt', [
        'ip' => $query->ipAddress,
      ]);
      return RefreshTokenResult::failed('Invalid or expired refresh token');
    }

    $userId = $payload['user_id'];

    if ($userId === '') {
      return RefreshTokenResult::failed('Invalid refresh token');
    }

    /** @var non-empty-string $userId */

    // Verify user is still active
    $userResult = $this->verifyUserActive($userId);
    if ($userResult !== null) {
      return $userResult;
    }

    /** @var list<string> $scopes */
    $scopes = $payload['scopes'];

    $tokens = $this->tokenService->generateTokens(
      userId: $userId,
      email: '',
      scopes: $scopes
    );

    $this->logger->info('Token refreshed successfully', [
      'user_id' => $userId,
      'ip' => $query->ipAddress,
    ]);

    return new RefreshTokenResult(
      success: true,
      userId: $userId,
      accessToken: $tokens['access_token'],
      refreshToken: $tokens['refresh_token'],
      tokenType: $tokens['token_type'],
      expiresIn: $tokens['expires_in'],
      scopes: $scopes,
    );
  }

  /**
   * Method verifyUserActive
   *
   * Verifies that the user is still active.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   *
   * @return RefreshTokenResult|null Error result if user is not active, null otherwise.
   */
  private function verifyUserActive(string $userId): ?RefreshTokenResult
  {
    try {
      /** @var GetUserResult $userResult */
      $userResult = $this->queryBus->ask(new GetUserQuery(id: $userId));

      if ($userResult->user === null) {
        $this->logger->warning('Refresh token for non-existent user', [
          'user_id' => $userId,
        ]);
        return RefreshTokenResult::failed('User not found');
      }

      if (!$userResult->user->canLogin()) {
        $this->logger->warning('Refresh token for inactive user', [
          'user_id' => $userId,
        ]);
        return RefreshTokenResult::failed('User account is not active');
      }

      return null;

    } catch (Throwable $e) {
      $this->logger->error('Error verifying user for refresh token', [
        'user_id' => $userId,
        'error' => $e->getMessage(),
      ]);
      return RefreshTokenResult::failed('Failed to verify user');
    }
  }

  /**
   * {@inheritDoc}
   */
  public function execute(RefreshTokenQuery $query): RefreshTokenResult
  {
    return $this->__invoke($query);
  }
  //#endregion
}
