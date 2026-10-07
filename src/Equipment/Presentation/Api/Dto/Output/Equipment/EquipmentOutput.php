<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Output\Equipment;

use ApiPlatform\Metadata\ApiProperty;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO EquipmentOutput.
 *
 * @category DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentOutput
{
  /**
   * @since 1.1.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  public ?string $name = null;

  /**
   * @since 1.1.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  public ?string $assetCode = null;

  /**
   * @since 1.1.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  public ?string $criticality = null;

  /**
   * @since 1.1.0
   *
   * @var list<array{key: string, value: string, unit: ?string}>
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  public array $technicalProperties = [];

  /**
   * @since 1.1.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  public ?string $predecessorEquipmentId = null;

  /**
   * @since 1.1.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  public ?string $successorEquipmentId = null;

  /**
   * Property intervention.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  public ?string $intervention = null;

  /**
   * Property recordStatus.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  public string $recordStatus = 'published';

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  public int $revision = 1;

  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $organizationId = '';

  /**
   * Property facilityId.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $facilityId = null;

  /**
   * Property type.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $type = '';

  /**
   * Property subType.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $subType = null;

  /**
   * Property brand.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $brand = null;

  /**
   * Property model.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $model = null;

  /**
   * Property serialNumber.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $serialNumber = null;

  /**
   * Property locationLabel.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $locationLabel = null;

  /**
   * Property facilityName.
   *
   * Display name of the assigned facility, resolved through the Facility
   * module's naming port — the Equipment module stores only the identifier.
   * Null when the equipment is unassigned, or when the facility could not be
   * resolved: an unresolved name is not a blank name.
   *
   * @since 1.1.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Display name of the assigned facility')]
  public ?string $facilityName = null;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = '';

  /**
   * Property installedAt.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $installedAt = null;

  /**
   * Property commissionedAt.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $commissionedAt = null;

  /**
   * Property tags.
   *
   * @since 1.0.0
   *
   * @var list<TagOutput>
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $tags = [];

  /**
   * Property maintenanceDueStatus.
   *
   * Compatibility alias of controlDueStatus, independent from service deadlines and operational condition.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $maintenanceDueStatus = 'unscheduled';

  /**
   * Property controlDueStatus
   *
   * Control operation status: unscheduled, up_to_date, due_soon or overdue.
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Due status of control operations, independent from service operations and anomalies.')]
  public string $controlDueStatus = 'unscheduled';

  /**
   * Property serviceDueStatus
   *
   * Service operation status: unscheduled, up_to_date, due_soon or overdue.
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Due status of service operations, independent from periodic controls.')]
  public string $serviceDueStatus = 'unscheduled';

  /**
   * Property controlNextDueAt
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Next control deadline in ISO 8601, when scheduled.')]
  public ?string $controlNextDueAt = null;

  /**
   * Property serviceNextDueAt
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Next service deadline in ISO 8601, when scheduled.')]
  public ?string $serviceNextDueAt = null;

  /**
   * Property planPosition.
   *
   * The equipment's position pinned on a floor plan attachment, or null when
   * unset. Populated on the **detail** read (`GET .../equipment/{id}`) and by
   * the plan-position mutation endpoint only — deliberately left unset on the
   * list/collection endpoints.
   *
   * @since 1.1.0
   *
   * @var ?array{attachmentId: string, x: float, y: float}
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Position pinned on a floor plan attachment (detail read only)')]
  public ?array $planPosition = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $updatedAt = '';
  // #endregion
}
