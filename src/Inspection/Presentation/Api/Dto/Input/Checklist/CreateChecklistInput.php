<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Input\Checklist;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class CreateChecklistInput
 *
 * Carries checklist metadata and items accepted by the checklist creation operation.
 *
 * @category DTO
 */
final class CreateChecklistInput
{
  // #region Properties
  /**
   * Checklist display name supplied at creation.
   */
  #[Assert\NotBlank(message: 'Checklist name is required.')]
  #[Assert\Length(max: 255)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Checklist name', required: true, example: 'Contrôle Extincteur')]
  public string $name = '';

  /**
   * Checklist version label supplied at creation.
   */
  #[Assert\NotBlank(message: 'Version is required.')]
  #[Assert\Length(max: 50)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Version label', required: true, example: '1.0')]
  public string $version = '';

  /**
   * Optional organization-facing reference code.
   */
  #[Assert\Length(max: 40)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional human-facing reference code, unique per organization', required: false, example: 'CHK-EXT-Q')]
  public ?string $referenceCode = null;

  /**
   * Previous checklist identifier when creating a revision.
   */
  #[Assert\Uuid]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Previous checklist in the same organization. Creates a distinct revision without changing existing inspections.')]
  public ?string $previousChecklistId = null;

  /**
   * Checklist item definitions supplied with the new checklist.
   *
   * @var list<ChecklistItemInput>
   */
  #[Assert\Valid]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Checklist items')]
  public array $items = [];
  // #endregion
}
