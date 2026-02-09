<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordReset\ConfirmPasswordReset;

use Shared\Application\Message\CommandMessage;

/**
 * Command ConfirmPasswordResetCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmPasswordResetCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ConfirmPasswordResetCommand class.
   *
   * @since 1.0.0
   *
   * @param string $token the challenge token from the request step
   * @param string $code the OTP code received by the user
   * @param string $newPassword the new password
   * @param string|null $ipAddress the client IP address
   */
  public function __construct(
    public readonly string $token,
    public readonly string $code,
    public readonly string $newPassword,
    public readonly ?string $ipAddress = null,
  ) {
  }
  // #endregion
}
