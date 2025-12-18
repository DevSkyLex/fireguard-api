<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\RegisterClient;

use Shared\Application\Message\ResultMessage;

/**
 * Result RegisterClientResult
 * @final
 *
 * Result returned after registering a new OAuth client.
 * Contains the client ID and the plain secret (shown only once).
 *
 * @category Result
 * @package OAuth\Application\UseCase\Command\RegisterClient
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegisterClientResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * RegisterClientResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $clientId The client ID (UUID).
   * @param string $clientSecret The plain client secret (shown only once).
   */
  public function __construct(
    public readonly string $clientId,
    public readonly string $clientSecret
  ) {
  }
  //#endregion
}
