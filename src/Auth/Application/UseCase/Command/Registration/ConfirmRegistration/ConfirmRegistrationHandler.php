<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Registration\ConfirmRegistration;

use Auth\Application\Port\Outbound\{JwtTokenServicePort, SessionTrackingPort};
use Auth\Domain\Event\Session\UserLoggedInEvent;
use Auth\Domain\Event\Token\TokenIssuedEvent;
use Auth\Domain\ValueObject\Scope\DefaultScopes;
use Otp\Application\Contract\Challenge\OtpPurpose;
use Otp\Application\Port\Inbound\Challenge\{ChallengeAccessPort, OtpChallengePort};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use UnexpectedValueException;
use User\Application\Port\Inbound\UserRegistrationPort;

use function array_key_exists;
use function is_array;
use function is_string;

/**
 * Handler ConfirmRegistrationHandler.
 *
 * Verifies the email-verification OTP, activates the account
 * through the User-owned activation capability and issues session tokens
 * so the freshly verified user is logged in automatically. Mirrors the
 * password-reset confirm handler plus the login token-issuance path.
 *
 * @category Handler
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
   * @param ChallengeAccessPort $otpRepository the OTP repository port
   * @param UserRegistrationPort $userRepository the user repository port
   * @param JwtTokenServicePort $tokenService the JWT token service
   * @param SessionTrackingPort $sessionTracking the session tracking port
   * @param EventDispatcherPort $eventDispatcher the audit event dispatcher
   */
  public function __construct(
    private readonly ChallengeAccessPort $otpRepository,
    private readonly UserRegistrationPort $userRepository,
    private OtpChallengePort $otpChallenge,
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
    $otp = $this->otpRepository->find($command->token);
    if (null === $otp || OtpPurpose::EMAIL_VERIFICATION !== $otp->purpose) {
      return ConfirmRegistrationResult::failed(
        message: 'Invalid or expired verification token.',
        errorCode: ConfirmRegistrationResult::ERROR_INVALID_TOKEN,
      );
    }
    $verification = $this->otpChallenge->verifyFor($command->token, $command->code, $otp->userId, OtpPurpose::EMAIL_VERIFICATION);
    if (!$verification->success) {
      return ConfirmRegistrationResult::failed(
        message: match ($verification->errorCode) {
          'expired' => 'The verification code has expired. Please request a new one.',
          'max_attempts_exceeded' => 'Maximum verification attempts exceeded. Please request a new code.',
          'invalid_token' => 'Invalid or expired verification token.',
          default => 'Invalid verification code. Please check and try again.',
        },
        errorCode: match ($verification->errorCode) {
          'expired' => ConfirmRegistrationResult::ERROR_EXPIRED,
          'max_attempts_exceeded' => ConfirmRegistrationResult::ERROR_MAX_ATTEMPTS,
          'invalid_token' => ConfirmRegistrationResult::ERROR_INVALID_TOKEN,
          default => ConfirmRegistrationResult::ERROR_INVALID_CODE,
        },
        attemptsRemaining: $verification->attemptsRemaining,
      );
    }

    return $this->activateAndLogIn($otp->userId, $otp->recipient, $command);
  }

  /**
   * Method activateAndLogIn.
   *
   * Confirms the OTP recipient against the account, activates it, and starts a session.
   *
   * @access private
   *
   * @param string $userId the pending account identifier
   * @param string $recipient the recipient verified by the OTP
   * @param ConfirmRegistrationCommand $command the request context used to record the session
   *
   * @return ConfirmRegistrationResult the account confirmation outcome
   */
  private function activateAndLogIn(string $userId, string $recipient, ConfirmRegistrationCommand $command): ConfirmRegistrationResult
  {
    $user = $this->userRepository->confirm($userId, $recipient);

    if (null === $user) {
      return ConfirmRegistrationResult::failed(
        message: 'User not found.',
        errorCode: ConfirmRegistrationResult::ERROR_INVALID_TOKEN,
      );
    }

    // Auto-login: issue session tokens for the now-active account.
    /** @var non-empty-string $userIdValue */
    $userIdValue = $userId;
    $email = $user->email;
    $scopes = DefaultScopes::USER_SCOPES;

    $tokens = $this->tokenService->generateTokens(
      userId: $userIdValue,
      email: $email,
      scopes: $scopes,
      rememberMe: false,
    );

    $accessTokenId = $this->recordSession($userIdValue, $tokens, $command->ipAddress, $command->userAgent);
    $this->dispatchLoginEvents($userIdValue, $email, $scopes, $accessTokenId, $tokens['expires_in'], $command->ipAddress);

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
   * @param string $accessTokenId the token identifier, never the bearer value
   * @param int $expiresIn token lifetime
   * @param string|null $ipAddress the client IP address
   *
   * @return void no return value
   */
  private function dispatchLoginEvents(string $userId, string $email, array $scopes, string $accessTokenId, int $expiresIn, ?string $ipAddress): void
  {
    $this->eventDispatcher->dispatch(new UserLoggedInEvent(
      userId: $userId,
      email: $email,
      ipAddress: $ipAddress,
    ));

    $this->eventDispatcher->dispatch(new TokenIssuedEvent(
      tokenId: $accessTokenId,
      grantType: 'registration',
      clientId: 'user_session',
      userId: $userId,
      scopes: $scopes,
      expiresIn: $expiresIn,
    ));
  }

  /**
   * Method recordSession.
   *
   * Persists the revocation anchor before exposing the auto-issued tokens.
   *
   * @param string $userId the user ID
   * @param array{access_token: string, refresh_token: string, access_token_id?: string, refresh_token_id?: string} $tokens issued tokens
   * @param string|null $ipAddress the client IP address
   * @param string|null $userAgent the client user agent
   *
   * @return string the persisted access token identifier, suitable for audit
   */
  private function recordSession(string $userId, array $tokens, ?string $ipAddress, ?string $userAgent): string
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

    if (null === $accessTokenId || '' === $accessTokenId || null === $refreshTokenId || '' === $refreshTokenId) {
      throw new UnexpectedValueException('Session token identifiers are required.');
    }
    $this->sessionTracking->recordSession(
      userId: $userId,
      ipAddress: $ipAddress ?? '127.0.0.1',
      userAgent: $userAgent ?? 'unknown',
      accessTokenId: $accessTokenId,
      refreshTokenId: $refreshTokenId,
      rememberMe: false,
    );

    return $accessTokenId;
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
