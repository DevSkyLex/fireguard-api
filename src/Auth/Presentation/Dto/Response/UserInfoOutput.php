<?php

declare(strict_types=1);

namespace Auth\Presentation\Dto\Response;

use Auth\Presentation\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO UserInfoOutput
 * @final
 *
 * @category Dto
 * @package Auth\Presentation\Dto\Response
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserInfoOutput
{
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $sub = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $name = null;

  #[SerializedName('given_name')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $givenName = null;

  #[SerializedName('family_name')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $familyName = null;

  #[SerializedName('preferred_username')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $preferredUsername = null;

  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?string $email = null;

  #[SerializedName('email_verified')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?bool $emailVerified = null;

  #[SerializedName('updated_at')]
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public ?int $updatedAt = null;
}
