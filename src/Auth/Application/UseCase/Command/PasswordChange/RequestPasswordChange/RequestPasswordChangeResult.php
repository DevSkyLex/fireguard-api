<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordChange\RequestPasswordChange;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Class RequestPasswordChangeResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPasswordChangeResult implements ResultMessage
{
  // #region Constants
  public const string ERROR_INVALID_PASSWORD = 'invalid_password';

  public const string ERROR_USER_NOT_FOUND = 'user_not_found';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $success whether the request was processed
   * @param string|null $message optional message (for user display)
   * @param string|null $errorCode error code when failed
   * @param string|null $challengeToken OTP challenge token (if available)
   * @param string|null $maskedRecipient masked destination (if available)
   * @param DateTimeImmutable|null $expiresAt OTP expiration timestamp (if available)
   * @param int|null $maxAttempts max verification attempts (if available)
   */
  public function __construct(
    public readonly bool $success,
    public readonly ?string $message = null,
    public readonly ?string $errorCode = null,
    public readonly ?string $challengeToken = null,
    public readonly ?string $maskedRecipient = null,
    public readonly ?DateTimeImmutable $expiresAt = null,
    public readonly ?int $maxAttempts = null,
  ) {
  }
  // #endregion

  // #region Static Constructors
  /**
   * Creates a successful result.
   *
   * @param string $challengeToken OTP challenge token
   * @param string|null $maskedRecipient masked destination
   * @param DateTimeImmutable|null $expiresAt OTP expiration timestamp
   * @param int|null $maxAttempts max verification attempts
   */
  public static function success(
    string $challengeToken,
    ?string $maskedRecipient = null,
    ?DateTimeImmutable $expiresAt = null,
    ?int $maxAttempts = null,
  ): self {
    return new self(
      success: true,
      message: 'A verification code has been sent to your email address.',
      challengeToken: $challengeToken,
      maskedRecipient: $maskedRecipient,
      expiresAt: $expiresAt,
      maxAttempts: $maxAttempts,
    );
  }

  /**
   * Creates a failed result.
   *
   * @param string $message error message
   * @param string $errorCode error code
   */
  public static function failed(string $message, string $errorCode): self
  {
    return new self(
      success: false,
      message: $message,
      errorCode: $errorCode,
    );
  }
  // #endregion
}
