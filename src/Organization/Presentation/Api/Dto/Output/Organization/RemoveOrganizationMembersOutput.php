<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO RemoveOrganizationMembersOutput.
 *
 * Reports the outcome of a batch member removal so the client can prune exactly
 * the members that were removed and surface a partial failure for the rest.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RemoveOrganizationMembersOutput
{
  // #region Properties
  /**
   * Property removedIds.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $removedIds = [];

  /**
   * Property failedIds.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $failedIds = [];
  // #endregion
}
