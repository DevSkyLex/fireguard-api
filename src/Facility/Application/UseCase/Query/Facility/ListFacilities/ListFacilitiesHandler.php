<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\ListFacilities;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityStatus, FacilityType};
use InvalidArgumentException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

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

    $facilities = $this->facilityRepository->findByOrganizationId(
      $organizationId,
      $query->includeArchived,
      $type,
      $status,
      $parentFacilityId,
      $query->code,
      $query->search,
      $query->sorting,
      $query->pagination->limit,
      $query->pagination->offset,
    );

    $total = $this->facilityRepository->countByOrganizationId(
      $organizationId,
      $query->includeArchived,
      $type,
      $status,
      $parentFacilityId,
      $query->code,
      $query->search,
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
      );
    }

    return new PaginatedResult(
      items: $results,
      total: $total,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
    );
  }
  // #endregion
}
