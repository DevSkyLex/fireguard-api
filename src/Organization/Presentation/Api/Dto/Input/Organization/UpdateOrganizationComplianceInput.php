<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateOrganizationComplianceInput.
 *
 * Partial organization compliance payload. Every field is optional; only the
 * provided fields are applied. Inside the two maps, setting an entry to `null`
 * removes the customization and reverts that key to the catalog default.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateOrganizationComplianceInput
{
  // #region Properties
  /**
   * Property nonConformitySlaDays.
   *
   * @since 1.0.0
   *
   * @var ?array<string, ?int>
   */
  #[Assert\Type('array')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Non-conformity resolution SLA in days per severity (low|medium|high|critical); null entry reverts to the default', required: false, example: ['critical' => 2, 'high' => null])]
  public ?array $nonConformitySlaDays = null;

  /**
   * Property inspectionPeriodicityDefaults.
   *
   * @since 1.0.0
   *
   * @var ?array<string, ?string>
   */
  #[Assert\Type('array')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Inspection periodicity (ISO-8601 duration) per equipment type value; null entry reverts to the default', required: false, example: ['fire_extinguisher' => 'P6M', 'camera' => null])]
  public ?array $inspectionPeriodicityDefaults = null;

  /**
   * Property reminderWindowDays.
   *
   * @since 1.0.0
   */
  #[Assert\Type('int')]
  #[Assert\Range(min: 1, max: 180)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Days before a due date during which reminders fire', required: false, example: 30)]
  public ?int $reminderWindowDays = null;
  // #endregion
}
