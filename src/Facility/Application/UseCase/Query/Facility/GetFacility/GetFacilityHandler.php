<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacility;

use Facility\Application\Port\Inbound\FacilityHierarchyPort;
use Facility\Application\Port\Outbound\{FacilityEquipmentDependencyPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilitySpatialValidityResolver;
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use Shared\Application\Message\QueryHandler;

use function is_string;

/**
 * UseCase GetFacilityHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the facility repository and equipment dependency capability used to assemble a facility detail view.
   *
   * @access public
   *
   * @param FacilityRepositoryPort $facilityRepository port used to load facility identity and hierarchy data
   * @param FacilityEquipmentDependencyPort $equipmentDependency port used to include dependent-equipment information in the detail
   *
   * @return void
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private FacilityEquipmentDependencyPort $equipmentDependency,
    private FacilitySpatialValidityResolver $spatial,
    private FacilityHierarchyPort $hierarchy,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param GetFacilityQuery $query the query payload
   *
   * @return GetFacilityResult the use case result
   */
  public function __invoke(GetFacilityQuery $query): GetFacilityResult
  {
    $facilityId = FacilityId::fromString($query->facilityId);
    $organizationId = FacilityOrganizationId::fromString($query->organizationId);

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($query->facilityId);
    }

    $geometry = $facility->planGeometryData();
    $attachmentId = $geometry['attachmentId'] ?? null;
    $context = $this->spatial->context((string) $organizationId, [(string) $facilityId], is_string($attachmentId) ? [$attachmentId] : []);

    return new GetFacilityResult(
      facilityId: (string) $facility->id(),
      organizationId: (string) $facility->organizationId(),
      parentFacilityId: $facility->parentFacilityId()?->__toString(),
      type: $facility->type()->value,
      name: (string) $facility->name(),
      code: $facility->code(),
      status: $facility->status()->value,
      address: $facility->address(),
      metadata: $facility->metadata(),
      createdAt: $facility->createdAt(),
      updatedAt: $facility->updatedAt(),
      hasChildren: $this->facilityRepository->countChildren($organizationId, $facilityId) > 0,
      latitude: $facility->coordinates()?->latitude(),
      longitude: $facility->coordinates()?->longitude(),
      equipmentCount: $this->equipmentDependency->countActiveEquipmentByFacility(
        (string) $organizationId,
        [(string) $facility->id()],
      )[(string) $facility->id()] ?? 0,
      path: $this->facilityRepository->findAncestors((string) $facility->id()),
      planGeometry: $this->spatial->geometryIsAuthorized($context, $geometry) ? $facility->planGeometry()?->toArray() : null,
      levelIndex: $facility->levelIndex(),
      elevationMeters: $facility->elevationMeters(),
      heightMeters: $facility->heightMeters(),
      geometryIssue: $this->spatial->geometryIssue($context, (string) $facilityId, $geometry),
      hierarchyIssues: $this->hierarchy->issuesFor((string) $organizationId, [(string) $facilityId])[(string) $facilityId] ?? [],
    );
  }
  // #endregion
}
