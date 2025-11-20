<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Query\ValidateClientCredentials;

use Shared\Application\Message\QueryMessage;

/**
 * Query ValidateClientCredentialsQuery
 * @final
 *
 * Query to validate OAuth client credentials.
 * Used during OAuth token exchange.
 *
 * @category Query
 * @package Client\Application\UseCase\Query\ValidateClientCredentials
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ValidateClientCredentialsQuery implements QueryMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the ValidateClientCredentialsQuery class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $clientId The client ID.
   * @param string $clientSecret The plain client secret.
   */
  public function __construct(
    public readonly string $clientId,
    public readonly string $clientSecret
  ) {}
  //#endregion
}
