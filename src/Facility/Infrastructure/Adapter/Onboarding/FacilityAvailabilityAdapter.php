<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Onboarding;

use Facility\Application\Port\Inbound\FacilityAvailabilityPort;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Application\UseCase\Query\Facility\ListFacilities\ListFacilitiesQuery;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Adapter FacilityAvailabilityAdapter.
 *
 * Keeps the owning query private while publishing its existence capability.
 *
 * @category Adapter
 */
final readonly class FacilityAvailabilityAdapter implements FacilityAvailabilityPort
{
  // #region Constructor
  /**
   * Method __construct.
   */
  public function __construct(private QueryBusPort $queries)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method hasFacility.
   */
  public function hasFacility(string $organizationId): bool
  {
    /** @var PaginatedResult<GetFacilityResult> $result */
    $result = $this->queries->ask(new ListFacilitiesQuery($organizationId, pagination: new Pagination(limit: 1)));

    return $result->total > 0;
  }
  // #endregion
}
