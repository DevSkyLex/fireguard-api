<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Session\Login;

use Auth\Application\Contract\User\UserAuthenticationResult;
use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionTrackingPort, TrustedDeviceCheckPort, UserAuthenticationPort};
use Auth\Application\Port\Outbound\Mfa\ChallengeGeneratorPort;
use Auth\Application\UseCase\Command\Mfa\MfaChallenge\MfaChallengeCommand;
use Auth\Domain\Event\Session\{LoginFailedEvent, UserLoggedInEvent};
use Auth\Domain\Event\Token\TokenIssuedEvent;
use Auth\Domain\ValueObject\Scope\DefaultScopes;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, RateLimiterPort};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function hash;
use function is_array;
use function sprintf;
use function strtolower;
use function substr;

/**
 * Handler LoginHandler.
 *
 * @category Handler
 *
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
   * @param JwtTokenServicePort $tokenService the JWT token service
   * @param ChallengeGeneratorPort $challengeGenerator the MFA challenge generator
   * @param SessionTrackingPort $sessionTracking the session tracking port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher
   * @param RateLimiterPort $rateLimiter the rate limiter
   * @param TrustedDeviceCheckPort $trustedDeviceCheck the trusted device check port
   * @param bool $mfaEnabled whether MFA is enabled globally
   */
  public function __construct(
    private readonly UserAuthenticationPort $userAuthentication,
    private readonly JwtTokenServicePort $tokenService,
    private readonly ChallengeGeneratorPort $challengeGenerator,
    private readonly SessionTrackingPort $sessionTracking,
    private readonly EventDispatcherPort $eventDispatcher,
    private readonly RateLimiterPort $rateLimiter,
    private readonly TrustedDeviceCheckPort $trustedDeviceCheck,
    #[Autowire('%env(bool:MFA_ENABLED)%')]
    private readonly bool $mfaEnabled,
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
    $rateLimit = $this->rateLimiter->consume($rateLimitKey);

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
      $scopes = DefaultScopes::USER_SCOPES;

      // Check if MFA is required (enabled globally and device is not trusted)
      if ($this->shouldRequireMfa($userId, $command->trustedDeviceToken)) {
        $challengeCommand = new MfaChallengeCommand(
          userId: $userId,
          purpose: 'login',
          channel: 'email',
          recipient: $email,
        );

        $challenge = $this->challengeGenerator->generate($challengeCommand);
        $preAuthToken = $this->tokenService->generatePreAuthToken(
          userId: $userId,
          challengeToken: $challenge->challengeToken,
          email: $email,
          scopes: $scopes,
          rememberMe: $command->rememberMe,
        );

        return new LoginResult(
          authenticated: true,
          userId: $userId,
          email: $email,
          scopes: $scopes,
          mfaRequired: true,
          mfaToken: $preAuthToken,
          challengeToken: $challenge->challengeToken,
          mfaMethod: $challengeCommand->channel,
          mfaDestination: \Shared\Domain\ValueObject\MaskedDestination::maskEmail($email),
        );
      }

      $tokens = $this->tokenService->generateTokens(
        userId: $userId,
        email: $email,
        scopes: $scopes,
        rememberMe: $command->rememberMe,
      );

      // Dispatch domain events
      $this->eventDispatcher->dispatch(new UserLoggedInEvent(
        userId: $userId,
        email: $email,
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

      $this->recordSession($command, $userId, $tokens);

      return new LoginResult(
        authenticated: true,
        userId: $authResult->userId,
        email: $email,
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

  /**
   * Method recordSession.
   *
   * Tracks a user session after login.
   *
   * @param LoginCommand $command the login command
   * @param string $userId the user ID
   * @param array{access_token: string, refresh_token: string} $tokens issued tokens
   *
   * @return void no return value
   */
  private function recordSession(LoginCommand $command, string $userId, array $tokens): void
  {
    $refreshPayload = $this->tokenService->decodeRefreshToken($tokens['refresh_token']);
    $accessTokenId = null;
    $refreshTokenId = null;
    if (is_array($refreshPayload)) {
      $accessTokenId = $refreshPayload['access_token_id'];
      $refreshTokenId = $refreshPayload['refresh_token_id'];
    }

    $ipAddress = $command->ipAddress ?? '127.0.0.1';
    $userAgent = $command->userAgent ?? 'unknown';

    try {
      $this->sessionTracking->recordSession(
        userId: $userId,
        ipAddress: $ipAddress,
        userAgent: $userAgent,
        accessTokenId: $accessTokenId,
        refreshTokenId: $refreshTokenId,
        rememberMe: $command->rememberMe,
      );
    } catch (Throwable) {
      // Best-effort session tracking; login must not fail.
    }
  }

  /**
   * Method shouldRequireMfa.
   *
   * Determines if MFA should be required for the login attempt.
   * MFA is bypassed if the device is trusted.
   *
   * @param string $userId the user ID
   * @param string|null $trustedDeviceToken the trusted device token
   *
   * @return bool true if MFA is required, false otherwise
   */
  private function shouldRequireMfa(string $userId, ?string $trustedDeviceToken): bool
  {
    // MFA not enabled globally
    if (!$this->mfaEnabled) {
      return false;
    }

    // No trusted device token provided
    if (null === $trustedDeviceToken || '' === $trustedDeviceToken) {
      return true;
    }

    // Check if the device is trusted for this user
    try {
      if ($this->trustedDeviceCheck->isTrusted($userId, $trustedDeviceToken)) {
        return false; // Device is trusted, skip MFA
      }
    } catch (Throwable) {
      // If check fails, require MFA for safety
    }

    return true;
  }
  // #endregion
}
