<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListEquipments;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceDueStatusPort, TagRepositoryPort};
use Equipment\Application\Port\Outbound\FacilityNamingPort;
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\Model\Tag\Tag;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentStatus, EquipmentType};
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

use function array_filter;
use function array_keys;
use function array_map;
use function array_slice;
use function array_values;
use function count;
use function in_array;

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
  // #region Constants
  /**
   * The four Maintenance due status values, mirrored here without importing
   * Maintenance's Domain layer — see `MaintenanceDueStatusPort`'s docblock.
   *
   * @var list<string>
   */
  private const array MAINTENANCE_DUE_STATUSES = ['unscheduled', 'up_to_date', 'due_soon', 'overdue'];

  /**
   * Upper bound for the in-memory scan `listFilteredByDueStatus()` requires.
   * Organizations are plan-quota bounded on equipment count, so this is a
   * generous safety net, not a practical limit.
   */
  private const int DUE_STATUS_FILTER_SCAN_LIMIT = 10_000;
  // #endregion

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
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    if (null !== $query->maintenanceDueStatus && !in_array($query->maintenanceDueStatus, self::MAINTENANCE_DUE_STATUSES, true)) {
      throw InvalidValueException::because('Invalid maintenanceDueStatus filter.');
    }

    if (null !== $query->maintenanceDueStatus) {
      return $this->listFilteredByDueStatus($organizationId, $type, $status, $query);
    }

    $equipments = $this->equipmentRepository->findByOrganizationId(
      $organizationId,
      $query->facilityId,
      $type,
      $status,
      $query->brand,
      $query->model,
      $query->subType,
      $query->search,
      $query->sorting,
      $query->pagination->limit,
      $query->pagination->offset,
    );

    $total = $this->equipmentRepository->countByOrganizationId(
      $organizationId,
      $query->facilityId,
      $type,
      $status,
      $query->brand,
      $query->model,
      $query->subType,
      $query->search,
    );

    $equipmentIds = array_map(
      static fn ($equipment): EquipmentId => $equipment->id(),
      $equipments,
    );

    $tagsByEquipmentId = $this->tagRepository->findTagsByEquipmentIds($equipmentIds);
    $dueStatusesByEquipmentId = $this->maintenanceDueStatusPort->dueStatusesForEquipment(
      (string) $organizationId,
      array_map(static fn (EquipmentId $id): string => (string) $id, $equipmentIds),
    );

    $facilityNames = $this->resolveFacilityNames($equipments);

    $results = [];
    foreach ($equipments as $equipment) {
      $facilityId = $equipment->facilityId()?->__toString();
      $results[] = $this->toResult(
        $equipment,
        $tagsByEquipmentId[(string) $equipment->id()] ?? [],
        $dueStatusesByEquipmentId[(string) $equipment->id()] ?? 'unscheduled',
        null !== $facilityId ? ($facilityNames[$facilityId] ?? null) : null,
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
   * Method listFilteredByDueStatus.
   *
   * The `maintenanceDueStatus` filter matches a value that lives in the
   * Maintenance module, not in the `equipment` table: it cannot be pushed
   * into the SQL `WHERE` clause the way the other filters are, without
   * Equipment depending on Maintenance's persistence. Instead: every
   * equipment matching the OTHER filters is loaded unbounded (up to
   * `DUE_STATUS_FILTER_SCAN_LIMIT`), the due status for that whole candidate
   * set is resolved in ONE batch call, the match is filtered in memory, then
   * paginated with `array_slice()`. This keeps the cross-module read to a
   * single call (never per row) at the cost of a wider (but quota-bounded)
   * equipment fetch only when this filter is used.
   *
   * @since 1.0.0
   *
   * @return PaginatedResult<GetEquipmentResult> the filtered, paginated page
   */
  private function listFilteredByDueStatus(
    EquipmentOrganizationId $organizationId,
    ?string $type,
    ?string $status,
    ListEquipmentsQuery $query,
  ): PaginatedResult {
    $candidates = $this->equipmentRepository->findByOrganizationId(
      $organizationId,
      $query->facilityId,
      $type,
      $status,
      $query->brand,
      $query->model,
      $query->subType,
      $query->search,
      $query->sorting,
      self::DUE_STATUS_FILTER_SCAN_LIMIT,
      0,
    );

    $dueStatusesByEquipmentId = $this->maintenanceDueStatusPort->dueStatusesForEquipment(
      (string) $organizationId,
      array_map(static fn ($equipment): string => (string) $equipment->id(), $candidates),
    );

    $matched = array_values(array_filter(
      $candidates,
      static fn ($equipment): bool => ($dueStatusesByEquipmentId[(string) $equipment->id()] ?? 'unscheduled') === $query->maintenanceDueStatus,
    ));

    $total = count($matched);
    $page = array_slice($matched, $query->pagination->offset, $query->pagination->limit);

    $tagsByEquipmentId = $this->tagRepository->findTagsByEquipmentIds(array_map(
      static fn ($equipment): EquipmentId => $equipment->id(),
      $page,
    ));

    $facilityNames = $this->resolveFacilityNames($page);

    $results = [];
    foreach ($page as $equipment) {
      $facilityId = $equipment->facilityId()?->__toString();
      $results[] = $this->toResult(
        $equipment,
        $tagsByEquipmentId[(string) $equipment->id()] ?? [],
        $dueStatusesByEquipmentId[(string) $equipment->id()] ?? 'unscheduled',
        null !== $facilityId ? ($facilityNames[$facilityId] ?? null) : null,
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
   * Method resolveFacilityNames.
   *
   * Resolves the display names of every facility assigned to the given page
   * of equipment in ONE batch call — one query per collection page, never one
   * per row.
   *
   * @since 1.0.0
   *
   * @param list<Equipment> $equipments the page of equipment to resolve names for
   *
   * @return array<string, string> display name keyed by facility identifier
   */
  private function resolveFacilityNames(array $equipments): array
  {
    $facilityIds = [];
    foreach ($equipments as $equipment) {
      $facilityId = $equipment->facilityId()?->__toString();
      if (null !== $facilityId) {
        $facilityIds[$facilityId] = true;
      }
    }

    if ([] === $facilityIds) {
      return [];
    }

    return $this->facilityNaming->findNamesByIds(array_keys($facilityIds));
  }

  /**
   * Method toResult.
   *
   * @since 1.0.0
   *
   * @param list<Tag> $tags the equipment's tags
   * @param string $maintenanceDueStatus the resolved maintenance due status value
   * @param ?string $facilityName the resolved facility display name, when assigned
   */
  private function toResult(Equipment $equipment, array $tags, string $maintenanceDueStatus, ?string $facilityName = null): GetEquipmentResult
  {
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
      maintenanceDueStatus: $maintenanceDueStatus,
      facilityName: $facilityName,
    );
  }
  // #endregion
}
