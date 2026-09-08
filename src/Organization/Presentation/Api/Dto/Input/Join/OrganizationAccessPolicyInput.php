<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Join;

use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO OrganizationAccessPolicyInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationAccessPolicyInput
{
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[Assert\Choice(choices: ['invitation_only', 'approval_required', 'automatic'])]
  public string $mode = 'invitation_only';

  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[Assert\Uuid]
  public ?string $roleId = null;
}
