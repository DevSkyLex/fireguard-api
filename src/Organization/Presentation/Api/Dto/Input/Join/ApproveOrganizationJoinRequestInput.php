<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Join;

use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO ApproveOrganizationJoinRequestInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApproveOrganizationJoinRequestInput
{
  /**
   * @var list<string>
   */
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[Assert\Count(min: 1, max: 20)]
  #[Assert\All([new Assert\Uuid()])]
  public array $roleIds = [];
}
