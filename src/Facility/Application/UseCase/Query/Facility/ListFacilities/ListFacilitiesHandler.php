<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\ListFacilities;

use Facility\Application\Contract\Facility\FacilityListCriteria;
use Facility\Application\Port\Inbound\FacilityHierarchyPort;
use Facility\Application\Port\Outbound\{FacilityEquipmentDependencyPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityStatus, FacilityType};
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;

/**
 * UseCase ListFacilitiesHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFacilitiesHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives facility and equipment-dependency capabilities used to assemble filtered facility results.
   *
   * @access public
   *
   * @param FacilityRepositoryPort $facilityRepository port used to retrieve facilities matching the list criteria
   * @param FacilityEquipmentDependencyPort $equipmentDependency port used to include equipment-dependent facility information
   * @param FacilityHierarchyPort $hierarchy resolves eligible parent candidates against the full organization graph
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

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param ListFacilitiesQuery $query the query payload
   *
   * @return PaginatedResult<GetFacilityResult> the use case result
   */
  public function __invoke(ListFacilitiesQuery $query): PaginatedResult
  {
    try {
      $organizationId = FacilityOrganizationId::fromString($query->organizationId);
      $type = null !== $query->type ? FacilityType::from($query->type)->value : null;
      $status = null !== $query->status ? FacilityStatus::from($query->status)->value : null;
      $parentFacilityId = null !== $query->parentFacilityId
        ? (string) FacilityId::fromString($query->parentFacilityId)
        : null;
      $parentForType = null !== $query->parentForType ? FacilityType::from($query->parentForType)->value : null;
      $parentForFacilityId = null !== $query->parentForFacilityId ? FacilityId::fromString($query->parentForFacilityId) : null;
      $interventionId = null !== $query->interventionId ? (string) FacilityId::fromString($query->interventionId) : null;
    } catch (InvalidValueException|ValueError $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    if ($query->rootsOnly && null !== $parentFacilityId) {
      throw InvalidValueException::because('rootsOnly cannot be combined with parentFacilityId.');
    }

    if (null !== $parentForType && null !== $parentForFacilityId) {
      throw InvalidValueException::because('parentForType cannot be combined with parentForFacilityId.');
    }
    if (null !== $interventionId && null === $parentForType && null === $parentForFacilityId) {
      throw InvalidValueException::because('interventionId requires parentForType or parentForFacilityId.');
    }

    $eligibleParentIds = null;
    if (null !== $parentForFacilityId) {
      $movingFacility = null === $interventionId ? $this->facilityRepository->findPublishedById($parentForFacilityId) : $this->facilityRepository->findById($parentForFacilityId);
      if (null === $movingFacility || (string) $movingFacility->organizationId() !== (string) $organizationId) {
        throw \Facility\Domain\Exception\FacilityNotFoundException::withId((string) $parentForFacilityId);
      }
      if (null !== $interventionId) {
        $movingContext = $this->facilityRepository->findProjectionContextsByFacilityIds($organizationId, [(string) $parentForFacilityId])[(string) $parentForFacilityId] ?? null;
        if (null === $movingContext || ('published' === $movingContext['recordStatus'] ? !$query->includePublishedParents : ('draft' !== $movingContext['recordStatus'] || $interventionId !== $movingContext['interventionId']))) {
          throw \Facility\Domain\Exception\FacilityNotFoundException::withId((string) $parentForFacilityId);
        }
      }
      $parentForType = $movingFacility->type()->value;
    }
    if (null !== $parentForType) {
      $eligibleParentIds = $this->hierarchy->eligibleParentIds((string) $organizationId, $parentForType, $parentForFacilityId?->__toString(), $interventionId);
    }

    $criteria = new FacilityListCriteria(
      type: $type,
      status: $status,
      parentFacilityId: $parentFacilityId,
      code: $query->code,
      search: $query->search,
      rootsOnly: $query->rootsOnly,
      hasCoordinates: $query->hasCoordinates,
      eligibleParentIds: $eligibleParentIds,
      parentInterventionId: $interventionId,
      includePublishedParents: $query->includePublishedParents,
    );

    $facilities = $this->facilityRepository->findByOrganizationId(
      organizationId: $organizationId,
      includeArchived: $query->includeArchived,
      criteria: $criteria,
      sorting: $query->sorting,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
    );

    $total = $this->facilityRepository->countByOrganizationId(
      organizationId: $organizationId,
      includeArchived: $query->includeArchived,
      criteria: $criteria,
    );

    $childCounts = $this->facilityRepository->countChildrenByParentIds(
      $organizationId,
      $this->facilityIds($facilities),
      $query->includeArchived,
    );

    $equipmentCounts = $this->equipmentDependency->countActiveEquipmentByFacility(
      (string) $organizationId,
      array_map(static fn (FacilityId $id): string => (string) $id, $this->facilityIds($facilities)),
    );

    $paths = $query->includePath ? $this->facilityRepository->findAncestorsByFacilityIds(
      $organizationId,
      array_map(static fn (FacilityId $id): string => (string) $id, $this->facilityIds($facilities)),
    ) : [];
    $contextIds = array_map(static fn (FacilityId $id): string => (string) $id, $this->facilityIds($facilities));
    foreach ($paths as $path) {
      foreach ($path as $ancestor) {
        $contextIds[] = $ancestor['id'];
      }
    }
    $projectionContexts = $this->facilityRepository->findProjectionContextsByFacilityIds($organizationId, array_values(array_unique($contextIds)));
    if (null !== $interventionId && !$query->includePublishedParents) {
      foreach ($paths as $id => $path) {
        $paths[$id] = array_values(array_filter($path, static fn (array $ancestor): bool => isset($projectionContexts[$ancestor['id']])
          && 'draft' === $projectionContexts[$ancestor['id']]['recordStatus'] && $interventionId === $projectionContexts[$ancestor['id']]['interventionId']));
      }
    }
    $hierarchyIssues = $this->hierarchy->issuesFor((string) $organizationId, array_map(
      static fn (FacilityId $id): string => (string) $id,
      $this->facilityIds($facilities),
    ));

    $results = [];

    foreach ($facilities as $facility) {
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
        latitude: $facility->coordinates()?->latitude(),
        longitude: $facility->coordinates()?->longitude(),
        equipmentCount: $equipmentCounts[(string) $facility->id()] ?? 0,
        path: $paths[(string) $facility->id()] ?? [],
        hierarchyIssues: $hierarchyIssues[(string) $facility->id()] ?? [],
        levelIndex: $facility->levelIndex(),
        elevationMeters: $facility->elevationMeters(),
        heightMeters: $facility->heightMeters(),
        recordStatus: $projectionContexts[(string) $facility->id()]['recordStatus'] ?? 'published',
        interventionId: $projectionContexts[(string) $facility->id()]['interventionId'] ?? null,
        revision: $projectionContexts[(string) $facility->id()]['revision'] ?? 1,
      );
    }

    return new PaginatedResult(
      items: $results,
      total: $total,
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
  // #endregion
}
