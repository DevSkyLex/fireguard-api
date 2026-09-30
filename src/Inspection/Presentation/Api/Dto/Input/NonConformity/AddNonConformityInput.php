<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Input\NonConformity;

use ApiPlatform\Metadata\ApiProperty;
use Inspection\Domain\ValueObject\NonConformitySeverity;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class AddNonConformityInput
 *
 * Carries the writable fields for adding a non-conformity.
 *
 * @category InputDto
 */
final class AddNonConformityInput
{
  // #region Properties
  /**
   * Property description
   *
   * Description of the observed non-conformity
   *
   * @access public
   */
  #[Assert\NotBlank(message: 'Description is required.')]
  #[Assert\Length(max: 2000)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Non-conformity description', required: true, example: 'Extincteur hors service')]
  public string $description = '';

  /**
   * Property severity
   *
   * Severity assigned to the non-conformity
   *
   * @access public
   */
  #[Assert\NotBlank(message: 'Severity is required.')]
  #[Assert\Choice(callback: [NonConformitySeverity::class, 'values'])]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Severity level', required: true, example: 'high')]
  public string $severity = '';

  /**
   * Property dueAt
   *
   * Optional resolution due date
   *
   * @access public
   */
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional due date for resolution (ISO 8601)', required: false, example: '2024-07-15T00:00:00+02:00')]
  public ?string $dueAt = null;

  /**
   * Property notes
   *
   * Optional notes for the non-conformity.
   *
   * @access public
   */
  #[Assert\Length(max: 5000)]
  #[Groups([InspectionSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional notes', required: false)]
  public ?string $notes = null;
  // #endregion
}
