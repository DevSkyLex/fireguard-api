<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\ListNonConformities;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListNonConformitiesQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListNonConformitiesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries inspection-scoped severity, status, search, pagination, and ordering filters for findings.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to authorize the query
   * @param string $inspectionId inspection whose findings are listed
   * @param ?string $severity optional finding severity filter
   * @param ?string $status optional finding lifecycle filter
   * @param Pagination $pagination requested page and page size
   * @param ?string $search optional text search
   * @param Sorting $sorting requested sort field and direction
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public ?string $severity = null,
    public ?string $status = null,
    public Pagination $pagination = new Pagination(),
    public ?string $search = null,
    public Sorting $sorting = new Sorting('createdAt', SortDirection::DESC),
  ) {
  }
  // #endregion
}
