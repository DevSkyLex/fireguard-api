<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Park\ListParkAnomalies;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListParkAnomaliesQuery.
 *
 * Shares the Parc family's client and facility scopes before applying pagination.
 *
 * @category UseCase
 */
final readonly class ListParkAnomaliesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string|null $family fire, safety or other; null selects all
   * @param string|null $customerId optional internal customer
   * @param string|null $facilityId optional published facility
   * @param Pagination $pagination result offset and limit
   * @param Sorting $sorting primary ordering with stable identity tie-breaker
   * @param bool $includeDescendants whether the facility includes its published descendants
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public ?string $family = null,
    public ?string $customerId = null,
    public ?string $facilityId = null,
    public Pagination $pagination = new Pagination(),
    public Sorting $sorting = new Sorting('createdAt', SortDirection::DESC),
    public bool $includeDescendants = true,
  ) {
  }
  // #endregion
}
