<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Query\ListClients;

use Client\Application\UseCase\Query\GetClient\GetClientResult;
use Shared\Application\Message\ResultMessage;

/**
 * Result ListClientsResult
 * @final
 *
 * Result of listing OAuth clients with pagination.
 *
 * @category Result
 * @package Client\Application\UseCase\Query\ListClients
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListClientsResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the ListClientsResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param list<GetClientResult> $clients The list of clients.
   * @param int $total The total count of clients.
   * @param int $offset The offset used for pagination.
   * @param int $limit The limit used for pagination.
   */
  public function __construct(
    public readonly array $clients,
    public readonly int $total,
    public readonly int $offset,
    public readonly int $limit
  ) {
  }
  //#endregion
}
