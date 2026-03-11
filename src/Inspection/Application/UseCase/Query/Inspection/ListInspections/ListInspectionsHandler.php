<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ListInspections;

use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Domain\ValueObject\{InspectionOrganizationId, InspectionResult, InspectionStatus};
use InvalidArgumentException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

use function count;

/**
 * UseCase ListInspectionsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInspectionsHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private NonConformityRepositoryPort $nonConformityRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @return PaginatedResult<GetInspectionResult>
   */
  public function __invoke(ListInspectionsQuery $query): PaginatedResult
  {
    try {
      $organizationId = InspectionOrganizationId::fromString($query->organizationId);
      $result = null !== $query->result ? InspectionResult::from($query->result)->value : null;
      $status = null !== $query->status ? InspectionStatus::from($query->status)->value : null;
    } catch (InvalidValueException|ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $inspections = $this->inspectionRepository->findByOrganizationId(
      $organizationId,
      $query->equipmentId,
      $query->facilityId,
      $result,
      $status,
    );

    $results = [];

    $inspectionIds = [];
    foreach ($inspections as $inspection) {
      $inspectionIds[] = (string) $inspection->id();
    }
    $countsByInspectionId = $this->nonConformityRepository->countsByInspectionIds($inspectionIds);

    foreach ($inspections as $inspection) {
      $nonConformitiesCount = $countsByInspectionId[(string) $inspection->id()] ?? 0;

      $results[] = new GetInspectionResult(
        inspectionId: (string) $inspection->id(),
        organizationId: (string) $inspection->organizationId(),
        equipmentId: (string) $inspection->equipmentId(),
        facilityId: $inspection->facilityId()?->__toString(),
        result: $inspection->result()->value,
        status: $inspection->status()->value,
        performedAt: $inspection->performedAt()->format('c'),
        inspectorType: $inspection->inspector()->type->value,
        inspectorName: $inspection->inspector()->name,
        inspectorUserId: $inspection->inspector()->userId,
        inspectorOrganizationName: $inspection->inspector()->organizationName,
        checklistId: $inspection->checklistId()?->__toString(),
        notes: $inspection->notes(),
        signature: $inspection->signature(),
        nonConformitiesCount: $nonConformitiesCount,
        createdAt: $inspection->createdAt(),
        updatedAt: $inspection->updatedAt(),
      );
    }

    $total = count($results);

    return new PaginatedResult(
      items: $results,
      total: $total,
      limit: $total,
      offset: 0,
    );
  }
  // #endregion
}
