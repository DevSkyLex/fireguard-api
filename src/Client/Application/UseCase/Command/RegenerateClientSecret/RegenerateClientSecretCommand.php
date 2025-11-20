<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Command\RegenerateClientSecret;

use Shared\Application\Message\CommandMessage;

/**
 * Command RegenerateClientSecretCommand
 * @final
 *
 * Command to regenerate an OAuth client's secret.
 *
 * @category Command
 * @package Client\Application\UseCase\Command\RegenerateClientSecret
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegenerateClientSecretCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the RegenerateClientSecretCommand class.
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
