<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Command\DeactivateClient;

use Shared\Application\Message\CommandMessage;

/**
 * Command DeactivateClientCommand
 * @final
 *
 * Command to deactivate an OAuth client.
 *
 * @category Command
 * @package Client\Application\UseCase\Command\DeactivateClient
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeactivateClientCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * DeactivateClientCommand class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $clientId The client ID.
   */
  public function __construct(
    public readonly string $clientId
  ) {}
  //#endregion
}
