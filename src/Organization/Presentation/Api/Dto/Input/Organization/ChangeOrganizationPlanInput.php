<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO ChangeOrganizationPlanInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ChangeOrganizationPlanInput
{
  // #region Properties
  /**
   * Property planId.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Plan identifier is required.')]
  #[Assert\Uuid(message: 'Plan identifier must be a valid UUID.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Identifier of the subscription plan to assign', required: true)]
  public string $planId = '';
  // #endregion
}
