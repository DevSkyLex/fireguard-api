<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO TransferOrganizationOwnershipInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TransferOrganizationOwnershipInput
{
  // #region Properties
  /**
   * Property newOwnerUserId.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'New owner user ID is required.')]
  #[Assert\Uuid(message: 'New owner user ID must be a valid UUID.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'User ID of the active member who will become the new owner', required: true, example: '550e8400-e29b-41d4-a716-446655440000')]
  public string $newOwnerUserId = '';

  /**
   * Property slug.
   *
   * Danger-zone confirmation, mirroring `DELETE /organizations/{id}`: the
   * organization's current slug, typed by the caller. Left un-validated by
   * the Symfony Validator (no `NotBlank`) so a missing or mismatched value
   * reaches the handler and is reported as a domain confirmation failure
   * (HTTP 422), not a generic 400.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Danger-zone confirmation: the organization\'s current slug, typed by the caller.', required: true)]
  public ?string $slug = null;
  // #endregion
}
