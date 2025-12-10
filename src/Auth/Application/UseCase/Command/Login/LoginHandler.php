<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Login;

use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Domain\Event\LoginFailedEvent;
use Auth\Domain\Event\TokenIssuedEvent;
use Auth\Domain\Event\UserLoggedInEvent;
use Auth\Domain\ValueObject\DefaultScopes;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Application\Port\Outbound\RateLimiterPort;
use Throwable;
use User\Application\UseCase\Query\AuthenticateUser\AuthenticateUserQuery;
use User\Application\UseCase\Query\AuthenticateUser\AuthenticateUserResult;

/**
 * Handler LoginHandler
 * @final
 *
 * Handles user authentication and
 * token generation.
 *
 * @category Handler
 * @package Auth\Application\UseCase\Command\Login
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LoginHandler implements CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * LoginHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus The query bus.
   * @param JwtTokenServicePort $tokenService The JWT token service.
   * @param EventDispatcherPort $eventDispatcher The event dispatcher.
   * @param RateLimiterPort $rateLimiter The rate limiter.
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly JwtTokenServicePort $tokenService,
    private readonly EventDispatcherPort $eventDispatcher,
    private readonly RateLimiterPort $rateLimiter,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the LoginCommand.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param LoginCommand $command The command.
   *
   * @return LoginResult The result.
   */
  public function __invoke(LoginCommand $command): LoginResult
  {
    // Check rate limit
    $rateLimitKey = $this->getRateLimitKey($command);
    $rateLimit = $this->rateLimiter->consume($rateLimitKey);

    if (!$rateLimit->accepted) {
      $this->eventDispatcher->dispatch(new LoginFailedEvent(
        email: $command->email,
        ipAddress: $command->ipAddress,
        reason: 'rate_limit_exceeded',
      ));

      return LoginResult::failed(
        sprintf('Too many login attempts. Please try again in %d seconds.', $rateLimit->retryAfter)
      );
    }

    try {
      /** @var AuthenticateUserResult $authResult */
      $authResult = $this->queryBus->ask(
        query: new AuthenticateUserQuery(
          username: $command->email,
          password: $command->password
        )
      );

      if (!$authResult->authenticated || $authResult->userId === null) {
        $this->eventDispatcher->dispatch(new LoginFailedEvent(
          email: $command->email,
          ipAddress: $command->ipAddress,
          reason: 'invalid_credentials',
        ));

        return LoginResult::failed();
      }

      $scopes = DefaultScopes::USER_SCOPES;

      /** @var non-empty-string $userId */
      $userId = $authResult->userId;

      $tokens = $this->tokenService->generateTokens(
        userId: $userId,
        email: $authResult->email ?? $command->email,
        scopes: $scopes
      );

      // Dispatch domain events
      $this->eventDispatcher->dispatch(new UserLoggedInEvent(
        userId: $userId,
        email: $authResult->email ?? $command->email,
        ipAddress: $command->ipAddress,
      ));

      $this->eventDispatcher->dispatch(new TokenIssuedEvent(
        tokenId: $tokens['access_token'],
        grantType: 'password',
        clientId: 'user_session',
        userId: $userId,
        scopes: $scopes,
        expiresIn: $tokens['expires_in'],
      ));

      return new LoginResult(
        authenticated: true,
        userId: $authResult->userId,
        email: $authResult->email,
        accessToken: $tokens['access_token'],
        refreshToken: $tokens['refresh_token'],
        tokenType: $tokens['token_type'],
        expiresIn: $tokens['expires_in'],
        scopes: $scopes,
      );
    } catch (Throwable) {
      $this->eventDispatcher->dispatch(new LoginFailedEvent(
        email: $command->email,
        ipAddress: $command->ipAddress,
        reason: 'internal_error',
      ));

      return LoginResult::failed();
    }
  }

  /**
   * Method getRateLimitKey
   *
   * Generates a rate limit key based on email and IP.
   *
   * @param LoginCommand $command The command.
   *
   * @return string The rate limit key.
   */
  private function getRateLimitKey(LoginCommand $command): string
  {
    $emailHash = hash('sha256', strtolower($command->email));
    $ipHash = hash('sha256', $command->ipAddress ?? 'unknown');

    return sprintf('login_%s_%s', substr($emailHash, 0, 16), substr($ipHash, 0, 16));
  }
  //#endregion
}
