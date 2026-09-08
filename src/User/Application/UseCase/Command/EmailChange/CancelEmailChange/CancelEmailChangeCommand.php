<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\EmailChange\CancelEmailChange;

use Shared\Application\Message\CommandMessage;

/**
 * Command CancelEmailChangeCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CancelEmailChangeCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CancelEmailChangeCommand class.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   */
  public function __construct(
    public string $userId,
  ) {
  }
  // #endregion
}
