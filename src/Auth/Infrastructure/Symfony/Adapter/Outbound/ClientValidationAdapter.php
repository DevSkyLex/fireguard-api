<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Symfony\Adapter\Outbound;

use Auth\Application\Port\Outbound\ClientValidationPort;
use Client\Application\UseCase\Query\ValidateClientCredentials\ValidateClientCredentialsQuery;
use Client\Application\UseCase\Query\ValidateClientCredentials\ValidateClientCredentialsResult;
use Shared\Application\Port\Inbound\QueryBusPort;
use Throwable;

/**
 * Adapter ClientValidationAdapter
 * @final
 *
 * Adapter for validating OAuth2 client credentials.
 * Uses the QueryBus to communicate with the Client module,
 * maintaining proper module isolation.
 *
 * Note: The dependency on Client\Application is acceptable here because
 * this is an Infrastructure adapter. The Application layer (Auth\Application)
 * only depends on the ClientValidationPort interface.
 *
 * @category Adapter
 * @package Auth\Infrastructure\Symfony\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientValidationAdapter implements ClientValidationPort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the ClientValidationAdapter class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus The query bus.
   */
  public function __construct(
    private QueryBusPort $queryBus
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method validateCredentials
   * {@inheritDoc}
   *
   * @access public
   * @since 1.0.0
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
  //#endregion
}
