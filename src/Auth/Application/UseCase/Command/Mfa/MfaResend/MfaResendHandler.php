<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Mfa\MfaResend;

use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Domain\Exception\Session\AuthorizationException;
use Auth\Domain\ValueObject\Scope\DefaultScopes;
use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort;
use Otp\Application\Service\ChallengeResendPolicy;
use Otp\Domain\ValueObject\ChallengeToken;
use Shared\Application\Message\CommandHandler;

use function array_filter;
use function array_values;
use function is_array;
use function is_bool;
use function is_string;

/**
 * Handler MfaResendHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaResendHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * MfaResendHandler class.
   *
   * @since 1.0.0
   *
   * @param JwtTokenServicePort $jwtService the JWT service
   * @param OtpRepositoryPort $otpRepository the OTP repository port
   * @param OtpChallengePort $otpChallenge the OTP challenge port
   */
  public function __construct(
    private readonly JwtTokenServicePort $jwtService,
    private readonly OtpRepositoryPort $otpRepository,
    private readonly OtpChallengePort $otpChallenge,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles MFA resend command.
   *
   * @since 1.0.0
   *
   * @param MfaResendCommand $command the command
   *
   * @throws AuthorizationException if pre-auth token is invalid
   *
   * @return MfaResendResult the result
   */
  public function __invoke(MfaResendCommand $command): MfaResendResult
  {
    $tokenClaims = $this->jwtService->decodePreAuthToken($command->preAuthToken);

    if (null === $tokenClaims) {
      throw AuthorizationException::invalidGrant('Invalid or expired pre-auth token');
    }

    $challengeToken = $tokenClaims['challenge_token'] ?? null;
    $userId = $tokenClaims['sub'] ?? null;
    $email = $tokenClaims['email'] ?? null;
    $scopesClaim = $tokenClaims['scopes'] ?? DefaultScopes::USER_SCOPES;
    $rememberMe = false;

    if (!is_string($challengeToken) || !is_string($userId) || '' === $userId) {
      throw AuthorizationException::invalidGrant('Invalid pre-auth token payload');
    }

    $scopes = DefaultScopes::USER_SCOPES;
    if (is_array($scopesClaim)) {
      $scopes = array_values(array_filter($scopesClaim, 'is_string'));
      if ([] === $scopes) {
        $scopes = DefaultScopes::USER_SCOPES;
      }
    }

    $rememberClaim = $tokenClaims['remember_me'] ?? null;
    if (is_bool($rememberClaim)) {
      $rememberMe = $rememberClaim;
    }

    $otp = $this->otpRepository->findByChallengeToken(
      token: ChallengeToken::fromString($challengeToken),
    );

    if (null === $otp || OtpPurpose::LOGIN->value !== $otp->purpose()->value || 'pending' !== $otp->status()) {
      return MfaResendResult::failed(
        message: 'Invalid or expired MFA challenge.',
        errorCode: MfaResendResult::ERROR_INVALID_CHALLENGE,
      );
    }

    $now = new DateTimeImmutable();
    $canResendIn = ChallengeResendPolicy::canResendIn(
      createdAt: $otp->createdAt(),
      now: $now,
    );

    if ($canResendIn > 0) {
      return MfaResendResult::failed(
        message: "Please wait {$canResendIn} seconds before resending.",
        errorCode: MfaResendResult::ERROR_RESEND_NOT_ALLOWED,
        retryAfter: $canResendIn,
      );
    }

    $channel = OtpChannel::from($otp->channel()->value);
    $challenge = $this->otpChallenge->generate(
      userId: $userId,
      purpose: OtpPurpose::LOGIN,
      channel: $channel,
      recipient: $otp->recipient(),
    );

    $newPreAuthToken = $this->jwtService->generatePreAuthToken(
      userId: $userId,
      challengeToken: $challenge->challengeToken,
      email: is_string($email) ? $email : '',
      scopes: $scopes,
      rememberMe: $rememberMe,
    );

    return MfaResendResult::success(
      preAuthToken: $newPreAuthToken,
      challengeToken: $challenge->challengeToken,
      mfaMethod: $channel->value,
      mfaDestination: $challenge->maskedRecipient,
      expiresAt: $challenge->expiresAt,
      maxAttempts: $challenge->maxAttempts,
      canResendIn: ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS,
      message: 'A new MFA code has been sent.',
    );
  }
  // #endregion
}
