<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\ListClients;

use Shared\Application\Message\QueryMessage;
use Shared\Application\Query\Pagination;

/**
 * Query ListClientsQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListClientsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListClientsQuery class.
   *
   * @since 1.0.0
   *
   * @param Pagination $pagination the pagination settings
   */
  public function __construct(
    public readonly Pagination $pagination,
  ) {
  }
  // #endregion
}
