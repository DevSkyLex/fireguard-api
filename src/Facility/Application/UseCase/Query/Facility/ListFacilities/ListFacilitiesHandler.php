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
    private ?\Customer\Application\Port\Inbound\CustomerLookupPort $customers = null,
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
    if (null !== $query->customerId && null === $this->customers?->find($query->customerId, $query->organizationId)) {
      throw new \Facility\Domain\Exception\FacilityCustomerScopeNotFoundException();
    }
    [$organizationId, $criteria] = $this->criteria($query);

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
    [$paths, $projectionContexts] = $this->pathsAndContexts($organizationId, $facilities, $query);
    $hierarchyIssues = $this->hierarchy->issuesFor((string) $organizationId, array_map(
      static fn (FacilityId $id): string => (string) $id,
      $this->facilityIds($facilities),
    ));

    $results = [];
    foreach ($facilities as $facility) {
      $results[] = $this->projectFacility($facility, $childCounts, $equipmentCounts, $paths, $hierarchyIssues, $projectionContexts);
    }

    return new PaginatedResult(
      items: $results,
      total: $total,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
    );
  }

  /**
   * Method criteria.
   *
   * Parses filters before resolving eligible parents, retaining the collection's validation order.
   *
   * @access private
   *
   * @param ListFacilitiesQuery $query requested collection filters
   *
   * @return array{FacilityOrganizationId, FacilityListCriteria} organization and validated criteria
   */
  private function criteria(ListFacilitiesQuery $query): array
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

    $this->assertCompatibleFilters($query);

    $eligibleParentIds = null;
    $movingFacilityId = $parentForFacilityId?->__toString();
    if (null !== $parentForFacilityId) {
      $parentForType = $this->movingFacilityType($organizationId, $parentForFacilityId, $interventionId, $query->includePublishedParents);
    }
    if (null !== $parentForType) {
      $eligibleParentIds = $this->hierarchy->eligibleParentIds((string) $organizationId, $parentForType, $movingFacilityId, $interventionId);
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
      customerId: $query->customerId,
    );

    return [$organizationId, $criteria];
  }

  /**
   * Method assertCompatibleFilters.
   *
   * Rejects mutually exclusive collection scopes after all identifiers and enums have been parsed.
   *
   * @access private
   *
   * @param ListFacilitiesQuery $query parsed filter values
   *
   * @return void
   */
  private function assertCompatibleFilters(ListFacilitiesQuery $query): void
  {
    if ($query->rootsOnly && null !== $query->parentFacilityId) {
      throw InvalidValueException::because('rootsOnly cannot be combined with parentFacilityId.');
    }
    if (null !== $query->parentForType && null !== $query->parentForFacilityId) {
      throw InvalidValueException::because('parentForType cannot be combined with parentForFacilityId.');
    }
    if (null !== $query->interventionId && null === $query->parentForType && null === $query->parentForFacilityId) {
      throw InvalidValueException::because('interventionId requires parentForType or parentForFacilityId.');
    }
  }

  /**
   * Method movingFacilityType.
   *
   * Resolves the move origin only when its publication context is readable in the requested scope.
   *
   * @access private
   *
   * @param FacilityOrganizationId $organizationId owning organization
   * @param FacilityId $facilityId move origin
   * @param ?string $interventionId authorized draft context, or published-only scope
   * @param bool $includePublished whether published parents are readable
   *
   * @return string type used by the hierarchy policy
   */
  private function movingFacilityType(FacilityOrganizationId $organizationId, FacilityId $facilityId, ?string $interventionId, bool $includePublished): string
  {
    $facility = null === $interventionId ? $this->facilityRepository->findPublishedById($facilityId) : $this->facilityRepository->findById($facilityId);
    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw \Facility\Domain\Exception\FacilityNotFoundException::withId((string) $facilityId);
    }
    if (null !== $interventionId) {
      $context = $this->facilityRepository->findProjectionContextsByFacilityIds($organizationId, [(string) $facilityId])[(string) $facilityId] ?? null;
      if (null === $context || ('published' === $context['recordStatus'] ? !$includePublished : ('draft' !== $context['recordStatus'] || $interventionId !== $context['interventionId']))) {
        throw \Facility\Domain\Exception\FacilityNotFoundException::withId((string) $facilityId);
      }
    }

    return $facility->type()->value;
  }

  /**
   * Method pathsAndContexts.
   *
   * Loads breadcrumbs and publication contexts in batches and removes inaccessible published ancestors.
   *
   * @access private
   *
   * @param FacilityOrganizationId $organizationId owning organization
   * @param list<Facility> $facilities current result page
   * @param ListFacilitiesQuery $query path and publication visibility
   *
   * @return array{array<string, list<array{id: string, name: string, type: string}>>, array<string, array{recordStatus: string, interventionId: ?string, revision: int}>} paths and contexts for the page
   */
  private function pathsAndContexts(FacilityOrganizationId $organizationId, array $facilities, ListFacilitiesQuery $query): array
  {
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
    if (null !== $query->interventionId && !$query->includePublishedParents) {
      foreach ($paths as $id => $path) {
        $paths[$id] = array_values(array_filter($path, static fn (array $ancestor): bool => isset($projectionContexts[$ancestor['id']])
          && 'draft' === $projectionContexts[$ancestor['id']]['recordStatus'] && $query->interventionId === $projectionContexts[$ancestor['id']]['interventionId']));
      }
    }

    return [$paths, $projectionContexts];
  }

  /**
   * Method projectFacility.
   *
   * Builds the canonical list projection from already-batched counts, paths and diagnostics.
   *
   * @access private
   *
   * @param Facility $facility result aggregate
   * @param array<string, int> $childCounts direct child counts
   * @param array<string, int> $equipmentCounts equipment counts
   * @param array<string, list<array{id: string, name: string, type: string}>> $paths readable breadcrumbs
   * @param array<string, list<string>> $hierarchyIssues hierarchy diagnostics
   * @param array<string, array{recordStatus: string, interventionId: ?string, revision: int}> $projectionContexts publication metadata
   *
   * @return GetFacilityResult canonical facility list item
   */
  private function projectFacility(Facility $facility, array $childCounts, array $equipmentCounts, array $paths, array $hierarchyIssues, array $projectionContexts): GetFacilityResult
  {
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
      hasChildren: ($childCounts[(string) $facility->id()] ?? 0) > 0,
      latitude: $facility->coordinates()?->latitude(),
      longitude: $facility->coordinates()?->longitude(),
      equipmentCount: $equipmentCounts[(string) $facility->id()] ?? 0,
      path: $paths[(string) $facility->id()] ?? [],
      hierarchyIssues: $hierarchyIssues[(string) $facility->id()] ?? [],
      levelIndex: $facility->levelIndex(),
      elevationMeters: $facility->elevationMeters(),
      heightMeters: $facility->heightMeters(),
      customerId: $facility->customerId(),
      recordStatus: $projectionContexts[(string) $facility->id()]['recordStatus'] ?? 'published',
      interventionId: $projectionContexts[(string) $facility->id()]['interventionId'] ?? null,
      revision: $projectionContexts[(string) $facility->id()]['revision'] ?? 1,
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
