<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityDescendants;

use Facility\Application\Port\Outbound\{FacilityEquipmentDependencyPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use Shared\Application\Message\QueryHandler;

use function array_map;

/**
 * Class GetFacilityDescendantsHandler
 *
 * Lists descendants after verifying organization ownership and maps equipment counts.
 *
 * @category Handler
 */
final readonly class GetFacilityDescendantsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Loads facility hierarchy and associated equipment counts through their owning ports.
   *
   * @access public
   *
   * @param FacilityRepositoryPort $facilityRepository loads the facility and descendants
   * @param FacilityEquipmentDependencyPort $equipmentDependency supplies equipment counts
   *
   * @return void
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private FacilityEquipmentDependencyPort $equipmentDependency,
  ) {
  }

  // #endregion
  // #region Methods
  /**
   * Method __invoke.
   *
   * Lists sorted descendants of a facility and maps their equipment counts.
   *
   * @access public
   *
   * @param GetFacilityDescendantsQuery $query the organization, facility, and list filters
   *
   * @return GetFacilityDescendantsResult the descendant facility results
   *
   * @throws FacilityNotFoundException when the facility is missing or outside the organization
   */
  public function __invoke(GetFacilityDescendantsQuery $query): GetFacilityDescendantsResult
  {
    $organizationId = FacilityOrganizationId::fromString($query->organizationId);
    $facilityId = FacilityId::fromString($query->facilityId);

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($query->facilityId);
    }

    $descendants = $this->facilityRepository->findDescendants(
      organizationId: $organizationId,
      facilityId: $facilityId,
      includeArchived: $query->includeArchived,
      search: $query->search,
      sorting: $query->sorting,
    );

    $childCounts = $this->facilityRepository->countChildrenByParentIds(
      $organizationId,
      $this->facilityIds($descendants),
      $query->includeArchived,
    );

    $equipmentCounts = $this->equipmentDependency->countActiveEquipmentByFacility(
      (string) $organizationId,
      array_map(static fn (FacilityId $id): string => (string) $id, $this->facilityIds($descendants)),
    );

    $results = [];
    foreach ($descendants as $facility) {
      $results[] = new GetFacilityResult(
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
        hasChildren: ($childCounts[(string) $facility->id()] ?? 0) > 0,
        equipmentCount: $equipmentCounts[(string) $facility->id()] ?? 0,
        levelIndex: $facility->levelIndex(),
      );
    }

    return new GetFacilityDescendantsResult($results);
  }

  /**
   * @param list<Facility> $facilities
   *
   * @return list<FacilityId>
   */
  private function facilityIds(array $facilities): array
  {
    $ids = [];
    foreach ($facilities as $facility) {
      $ids[] = $facility->id();
    }

    return $ids;
  }
  // #endregion
}
