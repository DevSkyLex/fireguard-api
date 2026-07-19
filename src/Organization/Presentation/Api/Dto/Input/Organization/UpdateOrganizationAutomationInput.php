<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateOrganizationAutomationInput.
 *
 * Partial organization automation toggles payload. Every flag is optional; only
 * the provided flags are applied on top of the current settings.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateOrganizationAutomationInput
{
  // #region Properties
  /**
   * Property autoCreateInterventionOnCriticalNc.
   *
   * @since 1.0.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Automatically create a draft corrective intervention when a critical non-conformity is recorded', required: false)]
  public ?bool $autoCreateInterventionOnCriticalNc = null;
  // #endregion
}
