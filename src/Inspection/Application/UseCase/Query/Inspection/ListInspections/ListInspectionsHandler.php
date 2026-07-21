<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ListInspections;

use DateTimeImmutable;
use Exception;
use Inspection\Application\Port\Outbound\{ChecklistRepositoryPort, EquipmentNamingPort, FacilityNamingPort};
use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Domain\ValueObject\{
  InspectionChecklistId,
  InspectionEquipmentId,
  InspectionFacilityId,
  InspectionOrganizationId,
  InspectionResult,
  InspectionStatus
};
use InvalidArgumentException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;
use ValueError;

/**
 * UseCase ListInspectionsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
use function array_keys;

final readonly class ListInspectionsHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private NonConformityRepositoryPort $nonConformityRepository,
    private EquipmentNamingPort $equipmentNaming,
    private FacilityNamingPort $facilityNaming,
    private ChecklistRepositoryPort $checklistRepository,
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
      $equipmentId = null !== $query->equipmentId ? (string) InspectionEquipmentId::fromString($query->equipmentId) : null;
      $facilityId = null !== $query->facilityId ? (string) InspectionFacilityId::fromString($query->facilityId) : null;
      $result = null !== $query->result ? InspectionResult::from($query->result)->value : null;
      $status = null !== $query->status ? InspectionStatus::from($query->status)->value : null;
      $performedAtFrom = null !== $query->performedAtFrom ? new DateTimeImmutable($query->performedAtFrom) : null;
      $performedAtTo = null !== $query->performedAtTo ? new DateTimeImmutable($query->performedAtTo) : null;
      $inspectorUserId = null !== $query->inspectorUserId ? (string) new Uuid($query->inspectorUserId) : null;
      $checklistId = null !== $query->checklistId ? (string) InspectionChecklistId::fromString($query->checklistId) : null;
    } catch (InvalidValueException|ValueError|Exception $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    if (null !== $performedAtFrom && null !== $performedAtTo && $performedAtFrom > $performedAtTo) {
      throw new InvalidArgumentException('performedAtFrom cannot be after performedAtTo.');
    }

    $inspections = $this->inspectionRepository->findByOrganizationId(
      $organizationId,
      $equipmentId,
      $facilityId,
      $result,
      $status,
      $performedAtFrom?->format(DateTimeImmutable::ATOM),
      $performedAtTo?->format(DateTimeImmutable::ATOM),
      $inspectorUserId,
      null,
      $checklistId,
      $query->search,
      $query->sorting,
      $query->pagination->limit,
      $query->pagination->offset,
    );

    $total = $this->inspectionRepository->countByOrganizationId(
      $organizationId,
      $equipmentId,
      $facilityId,
      $result,
      $status,
      $performedAtFrom?->format(DateTimeImmutable::ATOM),
      $performedAtTo?->format(DateTimeImmutable::ATOM),
      $inspectorUserId,
      null,
      $checklistId,
      $query->search,
    );

    $results = [];

    $inspectionIds = [];
    foreach ($inspections as $inspection) {
      $inspectionIds[] = (string) $inspection->id();
    }
    $countsByInspectionId = $this->nonConformityRepository->countsByInspectionIds($inspectionIds);

    // Resolved once for the whole page. One lookup per row is how a listing of
    // twenty inspections becomes forty extra queries.
    $equipmentIds = [];
    $facilityIds = [];
    foreach ($inspections as $inspection) {
      $equipmentIds[(string) $inspection->equipmentId()] = true;
      $facilityId = $inspection->facilityId()?->__toString();
      if (null !== $facilityId) {
        $facilityIds[$facilityId] = true;
      }
    }

    $checklistIds = [];
    foreach ($inspections as $inspection) {
      $checklistId = $inspection->checklistId()?->__toString();
      if (null !== $checklistId) {
        $checklistIds[$checklistId] = true;
      }
    }

    $checklistNames = $this->checklistRepository->findNamesByIds(array_keys($checklistIds));
    $serialNumbers = $this->equipmentNaming->findSerialNumbersByIds(array_keys($equipmentIds));
    $facilityNames = $this->facilityNaming->findNamesByIds(array_keys($facilityIds));

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
        equipmentSerialNumber: $serialNumbers[(string) $inspection->equipmentId()] ?? null,
        facilityName: null !== $inspection->facilityId()
          ? ($facilityNames[(string) $inspection->facilityId()] ?? null)
          : null,
        checklistName: null !== $inspection->checklistId()
          ? ($checklistNames[(string) $inspection->checklistId()] ?? null)
          : null,
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
