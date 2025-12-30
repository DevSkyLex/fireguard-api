<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\Client\RegenerateClientSecret;

use Shared\Application\Message\ResultMessage;

/**
 * Result RegenerateClientSecretResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegenerateClientSecretResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RegenerateClientSecretResult class.
   *
   * @since 1.0.0
   *
   * @param string $clientId the client ID
   * @param string $clientSecret the new plain client secret (shown only once)
   */
  public function __construct(
    public readonly string $clientId,
    public readonly string $clientSecret,
  ) {
  }
  // #endregion
}
