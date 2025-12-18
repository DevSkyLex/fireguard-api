<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\RegenerateClientSecret;

use Shared\Application\Message\ResultMessage;

/**
 * Result RegenerateClientSecretResult
 * @final
 *
 * Result of regenerating a client secret.
 *
 * @category Result
 * @package OAuth\Application\UseCase\Command\RegenerateClientSecret
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegenerateClientSecretResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the RegenerateClientSecretResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $clientId The client ID.
   * @param string $clientSecret The new plain client secret (shown only once).
   */
  public function __construct(
    public readonly string $clientId,
    public readonly string $clientSecret
  ) {}
  //#endregion
}
