<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipment;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceDueStatusPort, TagRepositoryPort};
use Equipment\Application\Port\Outbound\FacilityNamingPort;
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId};
use Maintenance\Application\Port\Inbound\MaintenanceOperationsDuePort;
use Shared\Application\Message\QueryHandler;

use function array_map;

/**
 * UseCase GetEquipmentHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives equipment, tag, maintenance-status, and facility-naming capabilities used to build the equipment view.
   *
   * @access public
   *
   * @param EquipmentRepositoryPort $equipmentRepository port used to load organization equipment
   * @param TagRepositoryPort $tagRepository port used to retrieve equipment tags
   * @param MaintenanceDueStatusPort $maintenanceDueStatusPort port used to resolve the current maintenance due status
   * @param FacilityNamingPort $facilityNaming port used to provide facility display names
   * @param ?MaintenanceOperationsDuePort $operationsDue independent control and service deadlines
   *
   * @return void
   */
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private TagRepositoryPort $tagRepository,
    private MaintenanceDueStatusPort $maintenanceDueStatusPort,
    private FacilityNamingPort $facilityNaming,
    private ?MaintenanceOperationsDuePort $operationsDue = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(GetEquipmentQuery $query): GetEquipmentResult
  {
    $equipmentId = EquipmentId::fromString($query->equipmentId);
    $organizationId = EquipmentOrganizationId::fromString($query->organizationId);

    $equipment = $this->equipmentRepository->findById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($query->equipmentId);
    }

    $tags = $this->tagRepository->findByEquipmentId($equipmentId);

    $operationsDueByEquipment = $this->operationsDue?->forEquipment(
      (string) $equipment->organizationId(),
      [(string) $equipment->id()],
    ) ?? [];
    $due = $operationsDueByEquipment[(string) $equipment->id()] ?? null;
    $legacyStatuses = null === $this->operationsDue ? $this->maintenanceDueStatusPort->dueStatusesForEquipment(
      (string) $equipment->organizationId(),
      [(string) $equipment->id()],
    ) : [];
    $controlStatus = $due->controlDueStatus ?? ($legacyStatuses[(string) $equipment->id()] ?? 'unscheduled');

    return new GetEquipmentResult(
      equipmentId: (string) $equipment->id(),
      organizationId: (string) $equipment->organizationId(),
      facilityId: $equipment->facilityId()?->__toString(),
      type: $equipment->type()->value,
      subType: $equipment->subType(),
      brand: $equipment->brand(),
      model: $equipment->model(),
      serialNumber: $equipment->serialNumber(),
      locationLabel: $equipment->locationLabel(),
      name: $equipment->identity()->name,
      assetCode: $equipment->identity()->assetCode,
      criticality: $equipment->identity()->criticality,
      technicalProperties: $equipment->identity()->technicalProperties,
      predecessorEquipmentId: $equipment->predecessorEquipmentId(),
      successorEquipmentId: $equipment->successorEquipmentId(),
      status: $equipment->status()->value,
      installedAt: $equipment->installedAt()?->format('c'),
      commissionedAt: $equipment->commissionedAt()?->format('c'),
      tags: array_map(
        static fn ($tag): array => [
          'id' => (string) $tag->id(),
          'name' => $tag->name(),
          'organizationId' => (string) $tag->organizationId(),
        ],
        $tags,
      ),
      createdAt: $equipment->createdAt(),
      updatedAt: $equipment->updatedAt(),
      maintenanceDueStatus: $controlStatus,
      controlDueStatus: $controlStatus,
      serviceDueStatus: $due->serviceDueStatus ?? 'unscheduled',
      controlNextDueAt: $due?->controlNextDueAt?->format('c'),
      serviceNextDueAt: $due?->serviceNextDueAt?->format('c'),
      facilityName: null !== $equipment->facilityId()
        ? ($this->facilityNaming->findNamesByIds([(string) $equipment->facilityId()])[(string) $equipment->facilityId()] ?? null)
        : null,
      planPosition: $equipment->planPosition()?->toArray(),
    );
  }
  // #endregion
}
