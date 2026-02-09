<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordReset\RequestPasswordReset;

use Shared\Application\Message\CommandMessage;

/**
 * Command RequestPasswordResetCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPasswordResetCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RequestPasswordResetCommand class.
   *
   * @since 1.0.0
   *
   * @param string $email the user's email
   * @param string|null $ipAddress the client IP address
   */
  public function __construct(
    public readonly string $email,
    public readonly ?string $ipAddress = null,
  ) {
  }
  // #endregion
}
