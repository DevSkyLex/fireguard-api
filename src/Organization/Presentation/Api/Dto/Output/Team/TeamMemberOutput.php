<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Team;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO TeamMemberOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TeamMemberOutput
{
  // #region Properties
  /**
   * Property memberId.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $memberId = '';

  /**
   * Property role.
   *
   * Free-form membership label (e.g. "lead"). NOT an RBAC role.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $role = null;

  /**
   * Property addedAt.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $addedAt = '';
  // #endregion
}
