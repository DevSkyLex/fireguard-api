<?php

declare(strict_types=1);

namespace Auth\Presentation\Dto\Request;

use Auth\Presentation\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO LoginInput
 * @final
 *
 * @category Dto
 * @package Auth\Presentation\Dto\Request
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class LoginInput
{
  #[Assert\NotBlank(message: 'The email field is required.')]
  #[Assert\Email(message: 'The email must be a valid email address.')]
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $email = null;

  #[Assert\NotBlank(message: 'The password field is required.')]
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $password = null;

  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  #[SerializedName('remember_me')]
  public bool $rememberMe = false;
}
