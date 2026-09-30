<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Input\Inspection;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Domain\ValueObject\InspectionResult;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class EditInspectionInput
 *
 * Carries optional fields for updating an inspection.
 *
 * @category InputDto
 */
final class EditInspectionInput
{
  // #region Properties
  /**
   * Property equipmentId
   *
   * Optional identifier of the inspected equipment.
   *
   * @access public
   */
  #[Assert\Uuid(message: 'Equipment ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Equipment identifier', required: false)]
  public ?string $equipmentId = null;

  /**
   * Property facilityId
   *
   * Optional facility associated with the inspection.
   *
   * @access public
   */
  #[Assert\Uuid(message: 'Facility ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional facility identifier', required: false)]
  public ?string $facilityId = null;

  /**
   * Property checklistId
   *
   * Optional checklist used for the inspection.
   *
   * @access public
   */
  #[Assert\Uuid(message: 'Checklist ID must be a valid UUID.')]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional checklist identifier', required: false)]
  public ?string $checklistId = null;

  /**
   * Property result
   *
   * Optional inspection result value.
   *
   * @access public
   */
  #[Assert\Choice(callback: [InspectionResult::class, 'values'])]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Inspection result', required: false, example: 'pass')]
  public ?string $result = null;

  /**
   * Property performedAt
   *
   * Optional time when the inspection was performed.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Date the inspection was performed (ISO 8601)', required: false, example: '2024-06-15T10:00:00+02:00')]
  public ?string $performedAt = null;

  /**
   * Property notes
   *
   * Optional inspection notes.
   *
   * @access public
   */
  #[Assert\Length(max: 5000)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional free-form notes', required: false)]
  public ?string $notes = null;

  /**
   * Property signature
   *
   * Optional inspection signature data.
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional signature data', required: false)]
  public ?string $signature = null;
  // #endregion
}
