<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordReset\RequestPasswordReset;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Class RequestPasswordResetResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPasswordResetResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $success whether the request was processed
   * @param string|null $message optional message (for user display)
   * @param string|null $challengeToken OTP challenge token (if available)
   * @param string|null $maskedRecipient masked destination (if available)
   * @param DateTimeImmutable|null $expiresAt OTP expiration timestamp (if available)
   * @param int|null $maxAttempts max verification attempts (if available)
   * @param int|null $canResendIn seconds until resend is allowed
   */
  public function __construct(
    public readonly bool $success,
    public readonly ?string $message = null,
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
   * @param string|null $challengeToken OTP challenge token (if available)
   * @param string|null $maskedRecipient masked destination (if available)
   * @param DateTimeImmutable|null $expiresAt OTP expiration timestamp (if available)
   * @param int|null $maxAttempts max verification attempts (if available)
   * @param int|null $canResendIn seconds until resend is allowed
   */
  public static function success(
    ?string $challengeToken = null,
    ?string $maskedRecipient = null,
    ?DateTimeImmutable $expiresAt = null,
    ?int $maxAttempts = null,
    ?int $canResendIn = null,
  ): self {
    return new self(
      success: true,
      message: 'If an account exists with this email, you will receive a password reset code.',
      challengeToken: $challengeToken,
      maskedRecipient: $maskedRecipient,
      expiresAt: $expiresAt,
      maxAttempts: $maxAttempts,
      canResendIn: $canResendIn,
    );
  }
  // #endregion
}
