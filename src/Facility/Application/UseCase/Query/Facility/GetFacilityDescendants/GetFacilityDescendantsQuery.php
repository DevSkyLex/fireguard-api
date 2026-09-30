<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityDescendants;

use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryMessage;

/**
 * Class GetFacilityDescendantsQuery
 *
 * Requests a sorted list of descendants for a facility within an organization.
 *
 * @category Query
 */
final readonly class GetFacilityDescendantsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the organization, parent facility, and descendant-list filters.
   *
   * @access public
   *
   * @param string $organizationId the owning organization identifier
   * @param string $facilityId the facility whose descendants are requested
   * @param bool $includeArchived whether archived descendants are included
   * @param string|null $search the optional descendant name search
   * @param Sorting $sorting the requested result ordering
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public bool $includeArchived = false,
    public ?string $search = null,
    public Sorting $sorting = new Sorting('name', SortDirection::ASC),
  ) {
  }
  // #endregion
}
