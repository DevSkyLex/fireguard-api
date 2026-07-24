<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\ListOrganizationNonConformities;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListOrganizationNonConformitiesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationNonConformitiesQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public ?string $severity = null,
    public ?string $status = null,
    public Pagination $pagination = new Pagination(),
    public ?string $search = null,
    public Sorting $sorting = new Sorting('createdAt', SortDirection::DESC),
  ) {
  }
  // #endregion
}
