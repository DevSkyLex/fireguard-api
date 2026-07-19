<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Input\Checklist;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateChecklistInput.
 *
 * Partial update payload (PATCH semantics): every field is optional and a
 * field omitted from the JSON body is left unchanged. The processor
 * inspects the raw request payload to tell "omitted" apart from
 * "explicitly cleared", mirroring `EditInspectionInput`.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateChecklistInput
{
  #[Assert\Length(max: 255)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Checklist name', required: false, example: 'Contrôle Extincteur')]
  public ?string $name = null;

  #[Assert\Length(max: 40)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional human-facing reference code, unique per organization', required: false, example: 'CHK-EXT-Q')]
  public ?string $referenceCode = null;

  /**
   * Full replacement item list. Rejected with a conflict when the checklist
   * is already referenced by an existing inspection (items must stay
   * immutable once used).
   *
   * @var ?list<ChecklistItemInput>
   */
  #[Assert\Valid]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Checklist items (full replacement list)', required: false)]
  public ?array $items = null;
}
