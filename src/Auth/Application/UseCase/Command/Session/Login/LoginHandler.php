<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Session\Login;

use Auth\Application\Contract\Token\SignInGrantType;
use Auth\Application\Contract\User\UserAuthenticationResult;
use Auth\Application\Port\Inbound\InteractiveSessionIssuerPort;
use Auth\Application\Port\Outbound\UserAuthenticationPort;
use Auth\Domain\Event\Session\LoginFailedEvent;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, RateLimiterPort};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyExecutionPort;
use Shared\Application\Port\Outbound\LoggerPort;
use Shared\Domain\ValueObject\RateLimitResult;
use Throwable;

use function hash;
use function sprintf;
use function strtolower;
use function substr;

/**
 * Handler LoginHandler.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LoginHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * LoginHandler class.
   *
   * @since 1.0.0
   *
   * @param UserAuthenticationPort $userAuthentication the user authentication port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher
   * @param RateLimiterPort $rateLimiter the rate limiter
   * @param InteractiveSessionIssuerPort $sessionIssuer shared session and MFA issuer
   * @param LoggerPort $logger the structured application logger
   */
  public function __construct(
    private readonly UserAuthenticationPort $userAuthentication,
    private readonly EventDispatcherPort $eventDispatcher,
    private readonly RateLimiterPort $rateLimiter,
    private readonly InteractiveSessionIssuerPort $sessionIssuer,
    private readonly LoggerPort $logger,
    private ?HttpIdempotencyExecutionPort $idempotency = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the LoginCommand.
   *
   * @since 1.0.0
   *
   * @param LoginCommand $command the command
   *
   * @return LoginResult the result
   */
  public function __invoke(LoginCommand $command): LoginResult
  {
    // Check rate limit
    $rateLimitKey = $this->getRateLimitKey($command);
    $rateLimit = null !== $this->idempotency?->executionLease() ? RateLimitResult::accepted() : $this->rateLimiter->consume($rateLimitKey);

    if (!$rateLimit->accepted) {
      $this->eventDispatcher->dispatch(new LoginFailedEvent(
        email: $command->email,
        ipAddress: $command->ipAddress,
        reason: 'rate_limit_exceeded',
      ));

      return LoginResult::failed(
        sprintf('Too many login attempts. Please try again in %d seconds.', $rateLimit->retryAfter),
        LoginResult::ERROR_RATE_LIMIT,
        $rateLimit->retryAfter,
      );
    }

    try {
      /** @var UserAuthenticationResult $authResult */
      $authResult = $this->userAuthentication->authenticate(
        email: $command->email,
        password: $command->password,
      );

      if (!$authResult->authenticated || null === $authResult->userId) {
        $this->eventDispatcher->dispatch(new LoginFailedEvent(
          email: $command->email,
          ipAddress: $command->ipAddress,
          reason: 'invalid_credentials',
        ));

        return LoginResult::failed();
      }

      /** @var non-empty-string $userId */
      $userId = $authResult->userId;
      $email = $authResult->email ?? $command->email;

      $session = $this->sessionIssuer->issue(
        userId: $userId,
        email: $email,
        ipAddress: $command->ipAddress,
        userAgent: $command->userAgent,
        trustedDeviceToken: $command->trustedDeviceToken,
        rememberMe: $command->rememberMe,
        grantType: SignInGrantType::PASSWORD,
      );
      $result = new LoginResult(
        authenticated: $session->authenticated,
        userId: $session->userId,
        email: $session->email,
        accessToken: $session->accessToken,
        refreshToken: $session->refreshToken,
        tokenType: $session->tokenType,
        expiresIn: $session->expiresIn,
        scopes: $session->scopes,
        mfaRequired: $session->mfaRequired,
        mfaToken: $session->mfaToken,
        challengeToken: $session->challengeToken,
        mfaMethod: $session->mfaMethod,
        mfaDestination: $session->mfaDestination,
        errorMessage: $session->errorMessage,
        errorCode: $session->errorCode,
        rateLimitRetryAfter: $session->rateLimitRetryAfter,
      );
    } catch (Throwable $exception) {
      $this->logger->critical('Password authentication failed unexpectedly.', [
        'exception_class' => $exception::class,
      ]);
      $this->eventDispatcher->dispatch(new LoginFailedEvent(
        email: $command->email,
        ipAddress: $command->ipAddress,
        reason: 'internal_error',
      ));

      $result = LoginResult::failed();
    }

    return $result;
  }

  /**
   * Method getRateLimitKey.
   *
   * Generates a rate limit key based on email and IP.
   *
   * @param LoginCommand $command the command
   *
   * @return string the rate limit key
   */
  private function getRateLimitKey(LoginCommand $command): string
  {
    $emailHash = hash('sha256', strtolower($command->email));
    $ipHash = hash('sha256', $command->ipAddress ?? 'unknown');

    return sprintf('login_%s_%s', substr($emailHash, 0, 16), substr($ipHash, 0, 16));
  }

  // #endregion
}
