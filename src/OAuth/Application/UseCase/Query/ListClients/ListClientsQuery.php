<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\ListClients;

use Shared\Application\Message\QueryMessage;
use Shared\Application\Query\Pagination;

/**
 * Query ListClientsQuery
 * @final
 *
 * Query to list clients with pagination.
 *
 * @category Query
 * @package OAuth\Application\UseCase\Query\ListClients
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListClientsQuery implements QueryMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the ListClientsQuery class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Pagination $pagination The pagination settings.
   */
  public function __construct(
    public readonly Pagination $pagination
  ) {}
  //#endregion
}
