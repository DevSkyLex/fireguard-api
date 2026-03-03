<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Input\Equipment;

use ApiPlatform\Metadata\ApiProperty;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO AddAttachmentInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AddAttachmentInput
{
  // #region Properties
  /**
   * Property fileName.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'File name is required.')]
  #[Assert\Length(max: 255)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Original file name', required: true, example: 'maintenance_report.pdf')]
  public string $fileName = '';

  /**
   * Property content.
   *
   * Base64-encoded file contents.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'File content is required.')]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Base64-encoded file contents', required: true)]
  public string $content = '';

  /**
   * Property mimeType.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'MIME type is required.')]
  #[Assert\Length(max: 100)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'MIME type', required: true, example: 'application/pdf')]
  public string $mimeType = '';

  /**
   * Property label.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 255)]
  #[Groups([EquipmentSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional human-readable label', required: false, example: 'Annual maintenance report 2024')]
  public ?string $label = null;
  // #endregion
}
