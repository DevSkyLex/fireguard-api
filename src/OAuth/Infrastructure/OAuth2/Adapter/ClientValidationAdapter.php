<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\Adapter;

use OAuth\Application\Port\Outbound\ClientValidationPort;
use OAuth\Application\UseCase\Query\ValidateClientCredentials\ValidateClientCredentialsQuery;
use OAuth\Application\UseCase\Query\ValidateClientCredentials\ValidateClientCredentialsResult;
use Shared\Application\Port\Inbound\QueryBusPort;
use Throwable;

/**
 * Adapter ClientValidationAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientValidationAdapter implements ClientValidationPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ClientValidationAdapter class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method validateCredentials
   * {@inheritDoc}
   *
   * Validates client credentials using the Client module.
   *
   * @since 1.0.0
   *
   * @param string $clientId     the client identifier
   * @param string $clientSecret the client secret
   *
   * @return bool true if credentials are valid, false otherwise
   */
  public function validateCredentials(string $clientId, string $clientSecret): bool
  {
    try {
      /** @var ValidateClientCredentialsResult $result */
      $result = $this->queryBus->ask(new ValidateClientCredentialsQuery(
        clientId: $clientId,
        clientSecret: $clientSecret
      ));

      return $result->isValid;
    } catch (Throwable) {
      return false;
    }
  }
  // #endregion
}
