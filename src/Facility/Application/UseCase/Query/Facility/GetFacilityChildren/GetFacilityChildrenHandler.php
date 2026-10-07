<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityChildren;

use Facility\Application\Port\Inbound\FacilityHierarchyPort;
use Facility\Application\Port\Outbound\{FacilityEquipmentDependencyPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;

use function array_map;

/**
 * Class GetFacilityChildrenHandler
 *
 * Lists direct children for a facility after checking organization ownership.
 *
 * @category Handler
 */
final readonly class GetFacilityChildrenHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Loads a facility’s direct children and maps their equipment counts.
   *
   * @access public
   *
   * @param FacilityRepositoryPort $facilityRepository loads facilities and their children
   * @param FacilityEquipmentDependencyPort $equipmentDependency supplies child equipment counts
   * @param FacilityHierarchyPort $hierarchy resolves historical diagnostics in one organization graph read
   *
   * @return void
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private FacilityEquipmentDependencyPort $equipmentDependency,
    private FacilityHierarchyPort $hierarchy,
  ) {
  }
  // #endregion

  /**
   * @return PaginatedResult<GetFacilityResult>
   */
  public function __invoke(GetFacilityChildrenQuery $query): PaginatedResult
  {
    $organizationId = FacilityOrganizationId::fromString($query->organizationId);
    $facilityId = FacilityId::fromString($query->facilityId);

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($query->facilityId);
    }

    $children = $this->facilityRepository->findChildren(
      organizationId: $organizationId,
      facilityId: $facilityId,
      includeArchived: $query->includeArchived,
      search: $query->search,
      sorting: $query->sorting,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
    );

    $childCounts = $this->facilityRepository->countChildrenByParentIds(
      $organizationId,
      $this->facilityIds($children),
      $query->includeArchived,
    );

    $equipmentCounts = $this->equipmentDependency->countActiveEquipmentByFacility(
      (string) $organizationId,
      array_map(static fn (FacilityId $id): string => (string) $id, $this->facilityIds($children)),
    );

    $paths = $query->includePath ? $this->facilityRepository->findAncestorsByFacilityIds(
      $organizationId,
      array_map(static fn (FacilityId $id): string => (string) $id, $this->facilityIds($children)),
    ) : [];
    $projectionContexts = $this->facilityRepository->findProjectionContextsByFacilityIds($organizationId, array_map(
      static fn (FacilityId $id): string => (string) $id,
      $this->facilityIds($children),
    ));
    $hierarchyIssues = $this->hierarchy->issuesFor((string) $organizationId, array_map(
      static fn (FacilityId $id): string => (string) $id,
      $this->facilityIds($children),
    ));

    $results = [];
    foreach ($children as $child) {
      $results[] = new GetFacilityResult(
        facilityId: (string) $child->id(),
        organizationId: (string) $child->organizationId(),
        parentFacilityId: $child->parentFacilityId()?->__toString(),
        type: $child->type()->value,
        name: (string) $child->name(),
        code: $child->code(),
        status: $child->status()->value,
        address: $child->address(),
        metadata: $child->metadata(),
        createdAt: $child->createdAt(),
        updatedAt: $child->updatedAt(),
        hasChildren: ($childCounts[(string) $child->id()] ?? 0) > 0,
        equipmentCount: $equipmentCounts[(string) $child->id()] ?? 0,
        path: $paths[(string) $child->id()] ?? [],
        hierarchyIssues: $hierarchyIssues[(string) $child->id()] ?? [],
        levelIndex: $child->levelIndex(),
        elevationMeters: $child->elevationMeters(),
        heightMeters: $child->heightMeters(),
        customerId: $child->customerId(),
        recordStatus: $projectionContexts[(string) $child->id()]['recordStatus'] ?? 'published',
        interventionId: $projectionContexts[(string) $child->id()]['interventionId'] ?? null,
        revision: $projectionContexts[(string) $child->id()]['revision'] ?? 1,
      );
    }

    return new PaginatedResult(
      items: $results,
      total: $this->facilityRepository->countChildren(
        organizationId: $organizationId,
        facilityId: $facilityId,
        includeArchived: $query->includeArchived,
        search: $query->search,
      ),
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
    );
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
}
