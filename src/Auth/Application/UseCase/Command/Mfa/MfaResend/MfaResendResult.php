<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Mfa\MfaResend;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Result MfaResendResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaResendResult implements ResultMessage
{
  // #region Constants
  public const string ERROR_INVALID_CHALLENGE = 'invalid_challenge';

  public const string ERROR_RESEND_NOT_ALLOWED = 'resend_not_allowed';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $success whether resend succeeded
   * @param string|null $message optional message
   * @param string|null $errorCode error code when failed
   * @param int $retryAfter retry-after seconds when rate limited
   * @param string|null $preAuthToken new pre-auth token
   * @param string|null $challengeToken new challenge token
   * @param string|null $mfaMethod MFA method (email, sms, totp)
   * @param string|null $mfaDestination masked destination
   * @param DateTimeImmutable|null $expiresAt OTP expiration timestamp
   * @param int|null $maxAttempts max verification attempts
   * @param int|null $canResendIn seconds until resend is allowed
   */
  public function __construct(
    public readonly bool $success,
    public readonly ?string $message = null,
    public readonly ?string $errorCode = null,
    public readonly int $retryAfter = 0,
    public readonly ?string $preAuthToken = null,
    public readonly ?string $challengeToken = null,
    public readonly ?string $mfaMethod = null,
    public readonly ?string $mfaDestination = null,
    public readonly ?DateTimeImmutable $expiresAt = null,
    public readonly ?int $maxAttempts = null,
    public readonly ?int $canResendIn = null,
  ) {
  }
  // #endregion

  // #region Static Constructors
  /**
   * Creates a successful result.
   *
   * @param string $preAuthToken new pre-auth token
   * @param string $challengeToken new challenge token
   * @param string $mfaMethod MFA method (email, sms, totp)
   * @param string $mfaDestination masked destination
   * @param DateTimeImmutable $expiresAt OTP expiration timestamp
   * @param int $maxAttempts max verification attempts
   * @param int $canResendIn seconds until resend is allowed
   * @param string $message success message
   */
  public static function success(
    string $preAuthToken,
    string $challengeToken,
    string $mfaMethod,
    string $mfaDestination,
    DateTimeImmutable $expiresAt,
    int $maxAttempts,
    int $canResendIn,
    string $message = 'A new MFA code has been sent.',
  ): self {
    return new self(
      success: true,
      message: $message,
      preAuthToken: $preAuthToken,
      challengeToken: $challengeToken,
      mfaMethod: $mfaMethod,
      mfaDestination: $mfaDestination,
      expiresAt: $expiresAt,
      maxAttempts: $maxAttempts,
      canResendIn: $canResendIn,
    );
  }

  /**
   * Creates a failed result.
   *
   * @param string $message error message
   * @param string $errorCode error code
   * @param int $retryAfter retry-after seconds
   */
  public static function failed(
    string $message,
    string $errorCode,
    int $retryAfter = 0,
  ): self {
    return new self(
      success: false,
      message: $message,
      errorCode: $errorCode,
      retryAfter: $retryAfter,
    );
  }
  // #endregion
}
