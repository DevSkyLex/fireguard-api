<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Join;

use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO OrganizationDomainInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationDomainInput
{
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 253)]
  public string $domain = '';
}
