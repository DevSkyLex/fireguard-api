<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\Checklist;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Class ChecklistItemOutput
 *
 * Represents one checklist item in checklist API responses.
 *
 * @category DTO
 */
final class ChecklistItemOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * Checklist item identifier.
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property label.
   *
   * Checklist item label.
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $label = '';

  /**
   * Property position.
   *
   * Position of the item in its checklist.
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $position = 0;

  /**
   * Property required.
   *
   * Whether this item is required during an inspection.
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $required = true;

  /**
   * Property description.
   *
   * Optional checklist item description.
   */
  #[Groups([InspectionSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $description = null;
  // #endregion
}
