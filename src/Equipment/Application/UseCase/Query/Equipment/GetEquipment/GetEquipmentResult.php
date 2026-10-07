<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipment;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetEquipmentResult.
 *
 * @category UseCase
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
   * @param string $maintenanceDueStatus compatibility control due status when the independent projection is unavailable
   * @param ?array{attachmentId: string, x: float, y: float} $planPosition the equipment's plan position, or null when unset
   * @param list<array{key: string, value: string, unit: ?string}> $technicalProperties descriptive properties
   * @param ?string $controlDueStatus the independent control due status; null denotes an older projection
   * @param string $serviceDueStatus the independent service due status
   * @param ?string $controlNextDueAt the next control deadline in ISO 8601
   * @param ?string $serviceNextDueAt the next service deadline in ISO 8601
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
    public ?string $name = null,
    public ?string $assetCode = null,
    public ?string $criticality = null,
    public array $technicalProperties = [],
    public ?string $predecessorEquipmentId = null,
    public ?string $successorEquipmentId = null,
    public ?string $controlDueStatus = null,
    public string $serviceDueStatus = 'unscheduled',
    public ?string $controlNextDueAt = null,
    public ?string $serviceNextDueAt = null,
  ) {
  }
  // #endregion
}
