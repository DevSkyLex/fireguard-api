<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Registration\ResendRegistration;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Class ResendRegistrationResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResendRegistrationResult implements ResultMessage
{
  // #region Constants
  public const string ERROR_INVALID_TOKEN = 'invalid_token';

  public const string ERROR_RESEND_NOT_ALLOWED = 'resend_not_allowed';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $success whether the resend succeeded
   * @param string|null $message optional message
   * @param string|null $errorCode error code when failed
   * @param int $retryAfter retry-after seconds when rate limited
   * @param string|null $challengeToken OTP challenge token (if success)
   * @param string|null $maskedRecipient masked destination (if success)
   * @param DateTimeImmutable|null $expiresAt OTP expiration timestamp (if success)
   * @param int|null $maxAttempts max verification attempts (if success)
   * @param int|null $canResendIn seconds until resend is allowed
   */
  public function __construct(
    public readonly bool $success,
    public readonly ?string $message = null,
    public readonly ?string $errorCode = null,
    public readonly int $retryAfter = 0,
    public readonly ?string $challengeToken = null,
    public readonly ?string $maskedRecipient = null,
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
   * @param string|null $challengeToken OTP challenge token
   * @param string|null $maskedRecipient masked destination
   * @param DateTimeImmutable|null $expiresAt OTP expiration timestamp
   * @param int|null $maxAttempts max verification attempts
   * @param int|null $canResendIn seconds until resend is allowed
   * @param string $message success message
   */
  public static function success(
    ?string $challengeToken = null,
    ?string $maskedRecipient = null,
    ?DateTimeImmutable $expiresAt = null,
    ?int $maxAttempts = null,
    ?int $canResendIn = null,
    string $message = 'A new verification code has been sent.',
  ): self {
    return new self(
      success: true,
      message: $message,
      challengeToken: $challengeToken,
      maskedRecipient: $maskedRecipient,
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
