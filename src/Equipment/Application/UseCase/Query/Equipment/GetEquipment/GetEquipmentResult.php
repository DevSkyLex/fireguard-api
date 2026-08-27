<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipment;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetEquipmentResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<array{id: string, name: string, organizationId: string}> $tags
   * @param string $maintenanceDueStatus the maintenance due status value (`unscheduled`|`up_to_date`|`due_soon`|`overdue`), resolved cross-module from the Maintenance module; `unscheduled` when the equipment has no maintenance schedule
   * @param ?array{attachmentId: string, x: float, y: float} $planPosition the equipment's plan position, or null when unset. Deliberately left `null` by `ListEquipmentsHandler` — this shape is shared with `GetEquipmentResult`'s single-item read, but only the detail endpoint populates it.
   */
  public function __construct(
    public string $equipmentId,
    public string $organizationId,
    public ?string $facilityId,
    public string $type,
    public ?string $subType,
    public ?string $brand,
    public ?string $model,
    public ?string $serialNumber,
    public ?string $locationLabel,
    public string $status,
    public ?string $installedAt,
    public ?string $commissionedAt,
    public array $tags,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public string $maintenanceDueStatus = 'unscheduled',
    public ?string $facilityName = null,
    public ?array $planPosition = null,
  ) {
  }
  // #endregion
}
