<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\ListClients;

use OAuth\Application\UseCase\Query\GetClient\GetClientResult;
use Shared\Application\Message\ResultMessage;

/**
 * Result ListClientsResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListClientsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListClientsResult class.
   *
   * @since 1.0.0
   *
   * @param list<GetClientResult> $clients the list of clients
   * @param int $total the total count of clients
   * @param int $offset the offset used for pagination
   * @param int $limit the limit used for pagination
   */
  public function __construct(
    public readonly array $clients,
    public readonly int $total,
    public readonly int $offset,
    public readonly int $limit,
  ) {
  }
  // #endregion
}
