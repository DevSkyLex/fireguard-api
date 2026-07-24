<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\GetInspection;

use Inspection\Application\Port\Outbound\{ChecklistRepositoryPort, EquipmentNamingPort, FacilityNamingPort};
use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId, NonConformityInspectionId};
use InvalidArgumentException;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase GetInspectionHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInspectionHandler implements QueryHandler
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
   */
  public function __invoke(GetInspectionQuery $query): GetInspectionResult
  {
    try {
      $inspectionId = InspectionId::fromString($query->inspectionId);
      $organizationId = InspectionOrganizationId::fromString($query->organizationId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $inspection = $this->inspectionRepository->findById($inspectionId);

    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($query->inspectionId);
    }

    $nonConformitiesCount = $this->nonConformityRepository->countByInspectionId(
      NonConformityInspectionId::fromString($query->inspectionId),
    );

    return new GetInspectionResult(
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
      equipmentSerialNumber: $this->equipmentNaming->findSerialNumbersByIds(
        [(string) $inspection->equipmentId()],
      )[(string) $inspection->equipmentId()] ?? null,
      checklistName: null !== $inspection->checklistId()
        ? ($this->checklistRepository->findNamesByIds(
          [(string) $inspection->checklistId()],
        )[(string) $inspection->checklistId()] ?? null)
        : null,
      facilityName: null !== $inspection->facilityId()
        ? ($this->facilityNaming->findNamesByIds(
          [(string) $inspection->facilityId()],
        )[(string) $inspection->facilityId()] ?? null)
        : null,
    );
  }
  // #endregion
}
