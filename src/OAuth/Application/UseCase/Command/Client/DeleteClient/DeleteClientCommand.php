<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\Client\DeleteClient;

use Shared\Application\Message\CommandMessage;

/**
 * Command DeleteClientCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteClientCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeleteClientCommand class.
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
