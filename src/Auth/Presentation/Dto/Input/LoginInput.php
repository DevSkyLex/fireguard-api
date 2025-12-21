<?php

declare(strict_types=1);

namespace Auth\Presentation\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO LoginInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class LoginInput
{
  // #region Properties
  /**
   * Property email.
   *
   * Email address of the user attempting to authenticate.
   * Must be a valid email format.
   *
   * @example john.doe@example.com
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The email field is required.')]
  #[Assert\Email(message: 'The email must be a valid email address.')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_WRITE])]
  #[SerializedName(serializedName: 'email')]
  #[ApiProperty(
    description: 'Email address of the user attempting to authenticate',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'john.doe@example.com',
    openapiContext: [
      'type'      => 'string',
      'format'    => 'email',
      'minLength' => 5,
      'maxLength' => 255,
      'pattern'   => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
    ],
    jsonSchemaContext: [
      'type'   => 'string',
      'format' => 'email',
    ],
  )]
  public ?string $email = null;

  /**
   * Property password.
   *
   * Password of the user. Must meet security requirements.
   * Minimum 8 characters with at least one uppercase, one lowercase,
   * one digit, and one special character.
   *
   * @example SecureP@ssw0rd!
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The password field is required.')]
  #[Assert\Length(
    min: 8,
    max: 128,
    minMessage: 'Password must be at least {{ limit }} characters long.',
    maxMessage: 'Password cannot exceed {{ limit }} characters.',
  )]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_WRITE])]
  #[SerializedName(serializedName: 'password')]
  #[ApiProperty(
    description: 'Password of the user (write-only, never returned in responses)',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'SecureP@ssw0rd!',
    openapiContext: [
      'type'      => 'string',
      'format'    => 'password',
      'minLength' => 8,
      'maxLength' => 128,
      'writeOnly' => true,
    ],
    jsonSchemaContext: [
      'type'      => 'string',
      'format'    => 'password',
      'writeOnly' => true,
    ],
  )]
  public ?string $password = null;

  /**
   * Property rememberMe.
   *
   * Whether to extend the session duration.
   * When true, the refresh token will have a longer expiration time (30 days).
   * When false, the session expires after the default period (1 day).
   *
   * @example false
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_WRITE])]
  #[SerializedName(serializedName: 'remember_me')]
  #[ApiProperty(
    description: 'Extend session duration (30 days if true, default 1 day if false)',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    default: false,
    example: false,
    openapiContext: [
      'type'      => 'boolean',
      'default'   => false,
      'nullable'  => false,
    ],
    jsonSchemaContext: [
      'type'    => 'boolean',
      'default' => false,
    ],
  )]
  public bool $rememberMe = false;
  // #endregion
}
