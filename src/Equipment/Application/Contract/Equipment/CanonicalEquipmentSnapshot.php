<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Equipment;

/**
 * Scalar canonical collection projection.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CanonicalEquipmentSnapshot
{
  // #region Constructor
  /**
   * @param list<array{key: string, value: string, unit: ?string}> $technicalProperties @since 1.0.0
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public ?string $interventionId,
    public string $recordStatus,
    public int $revision,
    public ?string $facilityId,
    public string $type,
    public ?string $subType,
    public ?string $brand,
    public ?string $model,
    public ?string $serialNumber,
    public ?string $locationLabel,
    public ?string $name,
    public ?string $assetCode,
    public ?string $criticality,
    public array $technicalProperties,
    public ?string $predecessorEquipmentId,
    public ?string $successorEquipmentId,
    public string $status,
    public ?string $installedAt,
    public ?string $commissionedAt,
    public string $createdAt,
    public string $updatedAt,
  ) {
  }
  // #endregion
}
