<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\ValidateClientCredentials;

use Shared\Application\Message\QueryMessage;

/**
 * Query ValidateClientCredentialsQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ValidateClientCredentialsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ValidateClientCredentialsQuery class.
   *
   * @since 1.0.0
   *
   * @param string $clientId the client ID
   * @param string $clientSecret the plain client secret
   */
  public function __construct(
    public readonly string $clientId,
    public readonly string $clientSecret,
  ) {
  }
  // #endregion
}
