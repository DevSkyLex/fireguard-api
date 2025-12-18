<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\ActivateClient;

use Shared\Application\Message\CommandMessage;

/**
 * Command ActivateClientCommand
 * @final
 *
 * Command to activate an OAuth client.
 *
 * @category Command
 * @package OAuth\Application\UseCase\Command\ActivateClient
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ActivateClientCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * ActivateClientCommand class.
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
