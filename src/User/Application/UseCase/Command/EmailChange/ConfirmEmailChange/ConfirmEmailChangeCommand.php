<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\EmailChange\ConfirmEmailChange;

use Shared\Application\Message\CommandMessage;

/**
 * Command ConfirmEmailChangeCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmEmailChangeCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ConfirmEmailChangeCommand class.
   *
   * @since 1.0.0
   *
   * @param string $token the raw confirmation token from the email link
   * @param string|null $ipAddress the client IP address
   */
  public function __construct(
    public string $token,
    public ?string $ipAddress = null,
  ) {
  }
  // #endregion
}
