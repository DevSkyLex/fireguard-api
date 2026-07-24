<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Registration\ConfirmRegistration;

use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionTrackingPort};
use Auth\Domain\Event\Session\UserLoggedInEvent;
use Auth\Domain\Event\Token\TokenIssuedEvent;
use Auth\Domain\ValueObject\Scope\DefaultScopes;
use Otp\Application\Contract\Challenge\OtpPurpose;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Domain\Exception\{OtpExpiredException, OtpMaxAttemptsException};
use Otp\Domain\ValueObject\ChallengeToken;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventBusPort, EventDispatcherPort};
use Shared\Domain\Service\EventIdProvider;
use Throwable;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

use function array_key_exists;
use function is_array;
use function is_string;

/**
 * Handler ConfirmRegistrationHandler.
 *
 * Verifies the email-verification OTP, activates the account
 * ({@see \User\Domain\Model\User\User::verifyEmail()}) and issues session tokens
 * so the freshly verified user is logged in automatically. Mirrors the
 * password-reset confirm handler plus the login token-issuance path.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmRegistrationHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ConfirmRegistrationHandler class.
   *
   * @since 1.0.0
   *
   * @param OtpRepositoryPort $otpRepository the OTP repository port
   * @param UserRepositoryPort $userRepository the user repository port
   * @param EventBusPort $eventBus the domain event bus
   * @param EventIdProvider $eventIdProvider the event ID provider
   * @param JwtTokenServicePort $tokenService the JWT token service
   * @param SessionTrackingPort $sessionTracking the session tracking port
   * @param EventDispatcherPort $eventDispatcher the audit event dispatcher
   */
  public function __construct(
    private readonly OtpRepositoryPort $otpRepository,
    private readonly UserRepositoryPort $userRepository,
    private readonly EventBusPort $eventBus,
    private readonly EventIdProvider $eventIdProvider,
    private readonly JwtTokenServicePort $tokenService,
    private readonly SessionTrackingPort $sessionTracking,
    private readonly EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the ConfirmRegistrationCommand.
   *
   * @since 1.0.0
   *
   * @param ConfirmRegistrationCommand $command the command
   *
   * @return ConfirmRegistrationResult the result
   */
  public function __invoke(ConfirmRegistrationCommand $command): ConfirmRegistrationResult
  {
    $challengeToken = ChallengeToken::fromString($command->token);
    $otp = $this->otpRepository->findByChallengeToken($challengeToken);

    if (null === $otp || OtpPurpose::EMAIL_VERIFICATION->value !== $otp->purpose()->value) {
      return ConfirmRegistrationResult::failed(
        message: 'Invalid or expired verification token.',
        errorCode: ConfirmRegistrationResult::ERROR_INVALID_TOKEN,
      );
    }

    try {
      $verified = $otp->verify($command->code);
      $this->otpRepository->save($otp);

      if (!$verified) {
        return ConfirmRegistrationResult::failed(
          message: 'Invalid verification code. Please check and try again.',
          errorCode: ConfirmRegistrationResult::ERROR_INVALID_CODE,
          attemptsRemaining: $otp->attemptsRemaining(),
        );
      }
    } catch (OtpExpiredException) {
      return ConfirmRegistrationResult::failed(
        message: 'The verification code has expired. Please request a new one.',
        errorCode: ConfirmRegistrationResult::ERROR_EXPIRED,
      );
    } catch (OtpMaxAttemptsException) {
      return ConfirmRegistrationResult::failed(
        message: 'Maximum verification attempts exceeded. Please request a new code.',
        errorCode: ConfirmRegistrationResult::ERROR_MAX_ATTEMPTS,
      );
    }

    $userId = new UserId($otp->userId());
    $user = $this->userRepository->findById($userId);

    if (null === $user) {
      return ConfirmRegistrationResult::failed(
        message: 'User not found.',
        errorCode: ConfirmRegistrationResult::ERROR_INVALID_TOKEN,
      );
    }

    // Activate the account (pending_verification -> active) and publish events.
    $user->verifyEmail(eventIdProvider: $this->eventIdProvider);
    $this->userRepository->save($user);

    foreach ($user->releaseEvents() as $event) {
      $this->eventBus->publish($event);
    }

    // Auto-login: issue session tokens for the now-active account.
    /** @var non-empty-string $userIdValue */
    $userIdValue = $userId->value;
    $email = $user->email()->value;
    $scopes = DefaultScopes::USER_SCOPES;

    $tokens = $this->tokenService->generateTokens(
      userId: $userIdValue,
      email: $email,
      scopes: $scopes,
      rememberMe: false,
    );

    $this->dispatchLoginEvents($userIdValue, $email, $scopes, $tokens, $command->ipAddress);
    $this->recordSession($userIdValue, $tokens, $command->ipAddress, $command->userAgent);

    return ConfirmRegistrationResult::success(
      accessToken: $tokens['access_token'],
      refreshToken: $tokens['refresh_token'],
      tokenType: $tokens['token_type'],
      expiresIn: $tokens['expires_in'],
      scopes: $scopes,
    );
  }

  /**
   * Method dispatchLoginEvents.
   *
   * Emits the same audit events as a regular login for the auto-issued session.
   *
   * @param non-empty-string $userId the user ID
   * @param string $email the user email
   * @param list<string> $scopes the granted scopes
   * @param array{access_token: string, expires_in: int} $tokens issued tokens
   * @param string|null $ipAddress the client IP address
   *
   * @return void no return value
   */
  private function dispatchLoginEvents(string $userId, string $email, array $scopes, array $tokens, ?string $ipAddress): void
  {
    $this->eventDispatcher->dispatch(new UserLoggedInEvent(
      userId: $userId,
      email: $email,
      ipAddress: $ipAddress,
    ));

    $this->eventDispatcher->dispatch(new TokenIssuedEvent(
      tokenId: $tokens['access_token'],
      grantType: 'registration',
      clientId: 'user_session',
      userId: $userId,
      scopes: $scopes,
      expiresIn: $tokens['expires_in'],
    ));
  }

  /**
   * Method recordSession.
   *
   * Tracks the auto-issued session (best-effort; verification must not fail).
   *
   * @param string $userId the user ID
   * @param array{access_token: string, refresh_token: string, access_token_id?: string, refresh_token_id?: string} $tokens issued tokens
   * @param string|null $ipAddress the client IP address
   * @param string|null $userAgent the client user agent
   *
   * @return void no return value
   */
  private function recordSession(string $userId, array $tokens, ?string $ipAddress, ?string $userAgent): void
  {
    $accessTokenId = $this->getTokenIdentifier($tokens, 'access_token_id');
    $refreshTokenId = $this->getTokenIdentifier($tokens, 'refresh_token_id');

    if (null === $accessTokenId || null === $refreshTokenId) {
      $refreshPayload = $this->tokenService->decodeRefreshToken($tokens['refresh_token']);
      if (is_array($refreshPayload)) {
        $accessTokenId = $refreshPayload['access_token_id'];
        $refreshTokenId = $refreshPayload['refresh_token_id'];
      }
    }

    try {
      $this->sessionTracking->recordSession(
        userId: $userId,
        ipAddress: $ipAddress ?? '127.0.0.1',
        userAgent: $userAgent ?? 'unknown',
        accessTokenId: $accessTokenId,
        refreshTokenId: $refreshTokenId,
        rememberMe: false,
      );
    } catch (Throwable) {
      // Best-effort session tracking; verification must not fail.
    }
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
