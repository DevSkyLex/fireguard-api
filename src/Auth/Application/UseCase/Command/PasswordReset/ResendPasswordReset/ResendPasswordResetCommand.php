<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordReset\ResendPasswordReset;

use Shared\Application\Message\CommandMessage;

/**
 * Command ResendPasswordResetCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResendPasswordResetCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ResendPasswordResetCommand class.
   *
   * @since 1.0.0
   *
   * @param string $token the challenge token
   * @param string|null $ipAddress the client IP address
   */
  public function __construct(
    public readonly string $token,
    public readonly ?string $ipAddress = null,
  ) {
  }
  // #endregion
}
