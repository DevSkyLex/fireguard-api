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
