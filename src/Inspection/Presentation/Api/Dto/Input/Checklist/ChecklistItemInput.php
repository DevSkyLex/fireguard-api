<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Input\Checklist;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ChecklistItemInput
 *
 * Carries the writable fields for a checklist item.
 *
 * @category InputDto
 */
final class ChecklistItemInput
{
  // #region Properties
  /**
   * Property label
   *
   * Checklist item label
   *
   * @access public
   */
  #[Assert\NotBlank(message: 'Item label is required.')]
  #[Assert\Length(max: 255)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Item label', required: true, example: 'Vérifier la pression')]
  public string $label = '';

  /**
   * Property description
   *
   * Optional checklist item description
   *
   * @access public
   */
  #[Assert\Length(max: 1000)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional item description', required: false)]
  public ?string $description = null;

  /**
   * Property required
   *
   * Whether the item is required
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether the item is required', required: false, example: true)]
  public bool $required = true;

  /**
   * Property position
   *
   * Display order for the item.
   *
   * @access public
   */
  #[Assert\PositiveOrZero]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Display position', required: false, example: 0)]
  public int $position = 0;
  // #endregion
}
