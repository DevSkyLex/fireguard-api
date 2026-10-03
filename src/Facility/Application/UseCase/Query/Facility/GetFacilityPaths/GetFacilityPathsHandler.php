<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityPaths;

use Facility\Application\Port\Inbound\FacilityHierarchyPort;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\ValueObject\FacilityOrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * Handler GetFacilityPathsHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityPathsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @since 1.0.0
   *
   * @param FacilityRepositoryPort $facilities resolves ancestor paths without per-row reads
   * @param FacilityHierarchyPort $hierarchy reports historical hierarchy inconsistencies
   */
  public function __construct(private FacilityRepositoryPort $facilities, private FacilityHierarchyPort $hierarchy)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetFacilityPathsQuery $query the collection page and requested enrichment
   *
   * @return GetFacilityPathsResult paths and historical diagnostics keyed by facility
   */
  public function __invoke(GetFacilityPathsQuery $query): GetFacilityPathsResult
  {
    return new GetFacilityPathsResult($query->includePath ? $this->facilities->findAncestorsByFacilityIds(
      FacilityOrganizationId::fromString($query->organizationId),
      $query->facilityIds,
    ) : [], $this->hierarchy->issuesFor($query->organizationId, $query->facilityIds));
  }
  // #endregion
}
