<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\EmailChange\RequestEmailChange;

use Shared\Application\Message\CommandMessage;

/**
 * Command RequestEmailChangeCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestEmailChangeCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RequestEmailChangeCommand class.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   * @param string $newEmail the requested new sign-in email address
   * @param string $currentPassword the current password to verify
   * @param string|null $ipAddress the client IP address
   */
  public function __construct(
    public string $userId,
    public string $newEmail,
    public string $currentPassword,
    public ?string $ipAddress = null,
  ) {
  }
  // #endregion
}
