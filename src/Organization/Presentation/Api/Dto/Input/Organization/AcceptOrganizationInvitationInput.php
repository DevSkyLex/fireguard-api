<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO AcceptOrganizationInvitationInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AcceptOrganizationInvitationInput
{
  // #region Properties
  /**
   * Property token.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Invitation token is required.')]
  #[Assert\Regex(
    pattern: '/^[a-f0-9]{64}$/',
    message: 'Invitation token format is invalid.',
  )]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Invitation token received by email', required: true, example: 'a4d8e8f04d3a2a59f5d8f29f6f4f25bc25d5ef8c9fef2a79328fa93ce31d5d88')]
  public string $token = '';
  // #endregion
}
