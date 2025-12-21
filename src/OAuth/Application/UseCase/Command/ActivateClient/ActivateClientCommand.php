<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\ActivateClient;

use Shared\Application\Message\CommandMessage;

/**
 * Command ActivateClientCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ActivateClientCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ActivateClientCommand class.
   *
   * @since 1.0.0
   *
   * @param string $clientId the client ID
   */
  public function __construct(
    public readonly string $clientId,
  ) {
  }
  // #endregion
}
