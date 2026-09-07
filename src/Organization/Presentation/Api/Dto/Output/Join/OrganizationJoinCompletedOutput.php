<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Join;

use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationJoinCompletedOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationJoinCompletedOutput
{
  /**
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  public string $organizationId = '';
}
