<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipment;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceDueStatusPort, TagRepositoryPort};
use Equipment\Application\Port\Outbound\FacilityNamingPort;
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;

/**
 * UseCase GetEquipmentHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private TagRepositoryPort $tagRepository,
    private MaintenanceDueStatusPort $maintenanceDueStatusPort,
    private FacilityNamingPort $facilityNaming,
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
    try {
      $equipmentId = EquipmentId::fromString($query->equipmentId);
      $organizationId = EquipmentOrganizationId::fromString($query->organizationId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $equipment = $this->equipmentRepository->findById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($query->equipmentId);
    }

    $tags = $this->tagRepository->findByEquipmentId($equipmentId);

    $dueStatuses = $this->maintenanceDueStatusPort->dueStatusesForEquipment(
      (string) $equipment->organizationId(),
      [(string) $equipment->id()],
    );

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
      maintenanceDueStatus: $dueStatuses[(string) $equipment->id()] ?? 'unscheduled',
      facilityName: null !== $equipment->facilityId()
        ? ($this->facilityNaming->findNamesByIds([(string) $equipment->facilityId()])[(string) $equipment->facilityId()] ?? null)
        : null,
      planPosition: $equipment->planPosition()?->toArray(),
    );
  }
  // #endregion
}
