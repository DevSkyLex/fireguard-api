<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListEquipments;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentStatus, EquipmentType};
use InvalidArgumentException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

use function array_map;

/**
 * UseCase ListEquipmentsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListEquipmentsHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private TagRepositoryPort $tagRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @return PaginatedResult<GetEquipmentResult>
   */
  public function __invoke(ListEquipmentsQuery $query): PaginatedResult
  {
    try {
      $organizationId = EquipmentOrganizationId::fromString($query->organizationId);
      $type = null !== $query->type ? EquipmentType::from($query->type)->value : null;
      $status = null !== $query->status ? EquipmentStatus::from($query->status)->value : null;
    } catch (InvalidValueException|ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $equipments = $this->equipmentRepository->findByOrganizationId(
      $organizationId,
      $query->facilityId,
      $type,
      $status,
      $query->pagination->limit,
      $query->pagination->offset,
    );

    $total = $this->equipmentRepository->countByOrganizationId(
      $organizationId,
      $query->facilityId,
      $type,
      $status,
    );

    $results = [];

    $equipmentIds = array_map(
      static fn ($equipment): EquipmentId => $equipment->id(),
      $equipments,
    );

    $tagsByEquipmentId = $this->tagRepository->findTagsByEquipmentIds($equipmentIds);

    foreach ($equipments as $equipment) {
      $tags = $tagsByEquipmentId[(string) $equipment->id()] ?? [];

      $results[] = new GetEquipmentResult(
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
