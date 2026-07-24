<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Registration\RegisterUser;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Class RegisterUserResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegisterUserResult implements ResultMessage
{
  // #region Constants
  /**
   * Error code returned when the email is already registered.
   */
  public const string ERROR_EMAIL_TAKEN = 'email_taken';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $success whether the registration request was accepted
   * @param string|null $message optional message (for user display)
   * @param string|null $errorCode error code when failed
   * @param string|null $challengeToken OTP challenge token (if accepted)
   * @param string|null $maskedRecipient masked destination (if accepted)
   * @param DateTimeImmutable|null $expiresAt OTP expiration timestamp (if accepted)
   * @param int|null $maxAttempts max verification attempts (if accepted)
   * @param int|null $canResendIn seconds until resend is allowed
   */
  public function __construct(
    public readonly bool $success,
    public readonly ?string $message = null,
    public readonly ?string $errorCode = null,
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
   * Creates a successful result with the email-verification challenge.
   *
   * @param string $challengeToken OTP challenge token
   * @param string $maskedRecipient masked destination
   * @param DateTimeImmutable $expiresAt OTP expiration timestamp
   * @param int $maxAttempts max verification attempts
   * @param int $canResendIn seconds until resend is allowed
   */
  public static function success(
    string $challengeToken,
    string $maskedRecipient,
    DateTimeImmutable $expiresAt,
    int $maxAttempts,
    int $canResendIn,
  ): self {
    return new self(
      success: true,
      message: 'Your account has been created. Enter the verification code we sent to your email.',
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
   */
  public static function failed(
    string $message,
    string $errorCode,
  ): self {
    return new self(
      success: false,
      message: $message,
      errorCode: $errorCode,
    );
  }
  // #endregion
}
