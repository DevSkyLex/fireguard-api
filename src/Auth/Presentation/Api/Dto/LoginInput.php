<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto;

use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO LoginInput
 * @final
 *
 * DTO for user login with email and password.
 *
 * @category DTO
 * @package Auth\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class LoginInput
{
  /**
   * Property email
   *
   * The user's email address.
   *
   * @var string|null
   */
  #[Assert\NotBlank(message: 'The email field is required.')]
  #[Assert\Email(message: 'The email must be a valid email address.')]
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $email = null;

  /**
   * Property password
   *
   * The user's password.
   *
   * @var string|null
   */
  #[Assert\NotBlank(message: 'The password field is required.')]
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  public ?string $password = null;

  /**
   * Property rememberMe
   *
   * If true, the refresh token will have a longer lifetime (30 days instead of 1 day).
   *
   * @var bool
   */
  #[Groups(groups: [AuthSerializationGroup::WRITE])]
  #[SerializedName('remember_me')]
  public bool $rememberMe = false;
}
