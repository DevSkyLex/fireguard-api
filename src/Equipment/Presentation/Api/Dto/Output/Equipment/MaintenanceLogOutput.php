<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Output\Equipment;

use ApiPlatform\Metadata\ApiProperty;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO MaintenanceLogOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MaintenanceLogOutput
{
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
   * Property equipmentId.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $equipmentId = '';

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $organizationId = '';

  /**
   * Property startedAt.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $startedAt = '';

  /**
   * Property completedAt.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $completedAt = null;

  /**
   * Property source.
   *
   * What produced this entry: `status_transition` or `intervention`.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $source = 'status_transition';

  /**
   * Property interventionId.
   *
   * The source intervention identifier, set for `intervention` entries only.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $interventionId = null;

  /**
   * Property interventionNumber.
   *
   * The source intervention's human-readable number, set for `intervention`
   * entries only.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?int $interventionNumber = null;

  /**
   * Property workItemAction.
   *
   * The linked work item action, or a derived label, set for
   * `intervention` entries only.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $workItemAction = null;

  /**
   * Property actorId.
   *
   * The acting user identifier, when known, set for `intervention` entries only.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $actorId = null;

  /**
   * Property summary.
   *
   * A free-form summary of the service performed, set for `intervention`
   * entries only.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $summary = null;
  // #endregion
}
