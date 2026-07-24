<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordChange\RequestPasswordChange;

use Shared\Application\Message\CommandMessage;

/**
 * Command RequestPasswordChangeCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPasswordChangeCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RequestPasswordChangeCommand class.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   * @param string $currentPassword the current password to verify
   * @param string|null $ipAddress the client IP address
   */
  public function __construct(
    public readonly string $userId,
    public readonly string $currentPassword,
    public readonly ?string $ipAddress = null,
  ) {
  }
  // #endregion
}
