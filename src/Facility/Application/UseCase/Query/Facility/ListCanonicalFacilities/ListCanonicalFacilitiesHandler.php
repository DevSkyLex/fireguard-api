<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\ListCanonicalFacilities;

use Facility\Application\Port\Inbound\FacilityHierarchyPort;
use Facility\Application\Port\Outbound\CanonicalFacilityCollectionPort;
use Facility\Application\Port\Outbound\Internal\FacilityRepositoryPort;
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityNotFoundException};
use Facility\Domain\ValueObject\FacilityOrganizationId;
use Intervention\Application\Port\Inbound\InterventionResourcesPort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;
use function max;
use function min;

/**
 * Handler ListCanonicalFacilitiesHandler.
 *
 * Resolves contextual filters, access and batch reads in Application.
 *
 * @category Handler
 */
final readonly class ListCanonicalFacilitiesHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct.
   */
  public function __construct(
    private CanonicalFacilityCollectionPort $collection,
    private FacilityRepositoryPort $facilities,
    private FacilityHierarchyPort $hierarchy,
    private OrganizationAuthorizationPort $authorization,
    private InterventionResourcesPort $interventions,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   */
  public function __invoke(ListCanonicalFacilitiesQuery $query): ListCanonicalFacilitiesResult
  {
    $organizationId = $query->organizationId ?? (null === $query->interventionId ? null : $this->interventions->interventionContext($query->interventionId)?->organizationId);
    if (null === $organizationId) {
      throw InvalidValueException::because('The organization or intervention filter is required.');
    }
    $decision = $this->authorization->resolveAccess($query->userId, $organizationId, 'organization.facilities.read');
    if ($decision->isOutsideScope()) {
      throw new FacilityNotFoundException('Facility not found.');
    }
    if (!$decision->isGranted()) {
      throw new FacilityAccessDeniedException('Missing organization.facilities.read permission.');
    }
    $page = max(1, $query->page);
    $perPage = max(1, min(100, $query->itemsPerPage));
    $result = $this->collection->page($organizationId, $query->interventionId, $query->recordStatus ?? (null === $query->interventionId ? 'published' : 'draft'), $page, $perPage);
    $ids = array_map(static fn ($item): string => $item->id, $result->items);

    return new ListCanonicalFacilitiesResult(
      $result,
      $page,
      $perPage,
      $query->includePath ? $this->facilities->findAncestorsByFacilityIds(FacilityOrganizationId::fromString($organizationId), $ids) : [],
      $this->hierarchy->issuesFor($organizationId, $ids),
    );
  }
  // #endregion
}
