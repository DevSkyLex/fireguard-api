<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\Inspection;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO InspectionOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionOutput
{
  /**
   * Property intervention.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  public ?string $intervention = null;

  /**
   * Property recordStatus.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  public string $recordStatus = 'published';

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  public int $revision = 1;

  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $organizationId = '';

  /**
   * Property equipmentId.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $equipmentId = '';

  /**
   * Property facilityId.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $facilityId = null;

  /**
   * Property equipmentSerialNumber.
   *
   * Serial number of the inspected equipment, resolved through the Equipment
   * module's naming port. The Inspection module stores only the identifier,
   * and a UUID names nothing to the agent holding the device.
   *
   * Null when the equipment records no serial number, or when it could not be
   * resolved.
   *
   * @since 1.1.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Serial number of the inspected equipment')]
  public ?string $equipmentSerialNumber = null;

  /**
   * Property facilityName.
   *
   * Display name of the facility the inspection took place in, resolved
   * through the Facility module's naming port.
   *
   * @since 1.1.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Display name of the inspected facility')]
  public ?string $facilityName = null;

  /**
   * Property checklistName.
   *
   * Display name of the checklist the inspection followed. Resolved on read
   * from the module's own checklist aggregate; the inspection record stores
   * only the identifier.
   *
   * @since 1.1.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Display name of the followed checklist')]
  public ?string $checklistName = null;

  /**
   * Property result.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $result = '';

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = '';

  /**
   * Property performedAt.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $performedAt = '';

  /**
   * Property inspector.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?InspectorOutput $inspector = null;

  /**
   * Property checklistId.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $checklistId = null;

  /**
   * Property notes.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $notes = null;

  /**
   * Property signature.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $signature = null;

  /**
   * Property nonConformitiesCount.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $nonConformitiesCount = 0;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $updatedAt = '';
}
