<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Query\GetClient;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetClientQuery
 * @final
 *
 * Query to retrieve a client by its ID.
 *
 * @category Query
 * @package Client\Application\UseCase\Query\GetClient
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetClientQuery implements QueryMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the GetClientQuery class.
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
