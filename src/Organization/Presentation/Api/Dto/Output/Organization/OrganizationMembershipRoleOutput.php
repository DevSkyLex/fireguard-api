<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationMembershipRoleOutput.
 *
 * One organization role assigned to the authenticated user inside an
 * organization, as exposed on the user's organization list
 * (`GET /api/organizations`) membership info.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationMembershipRoleOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * The organization role ID.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $id = '';

  /**
   * Property label.
   *
   * The organization role display label (role name).
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $label = '';
  // #endregion
}
