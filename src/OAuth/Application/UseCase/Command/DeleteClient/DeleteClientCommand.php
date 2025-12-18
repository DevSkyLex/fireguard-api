<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\DeleteClient;

use Shared\Application\Message\CommandMessage;

/**
 * Command DeleteClientCommand
 * @final
 *
 * Command to delete an OAuth client (soft delete).
 *
 * @category Command
 * @package OAuth\Application\UseCase\Command\DeleteClient
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteClientCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the DeleteClientCommand class.
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
