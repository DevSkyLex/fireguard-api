<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\ListFacilities;

use Facility\Application\Port\Outbound\{FacilityEquipmentDependencyPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityStatus, FacilityType};
use InvalidArgumentException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

use function array_map;

/**
 * UseCase ListFacilitiesHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFacilitiesHandler implements QueryHandler
{
  // #region Constructor
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
    } catch (InvalidValueException|ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    if ($query->rootsOnly && null !== $parentFacilityId) {
      throw new InvalidArgumentException('rootsOnly cannot be combined with parentFacilityId.');
    }

    $facilities = $this->facilityRepository->findByOrganizationId(
      organizationId: $organizationId,
      includeArchived: $query->includeArchived,
      type: $type,
      status: $status,
      parentFacilityId: $parentFacilityId,
      code: $query->code,
      search: $query->search,
      sorting: $query->sorting,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
      rootsOnly: $query->rootsOnly,
    );

    $total = $this->facilityRepository->countByOrganizationId(
      organizationId: $organizationId,
      includeArchived: $query->includeArchived,
      type: $type,
      status: $status,
      parentFacilityId: $parentFacilityId,
      code: $query->code,
      search: $query->search,
      rootsOnly: $query->rootsOnly,
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
