<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Dto\Output\Discovery;

use ApiPlatform\Metadata\ApiProperty;
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO UserInfoOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserInfoOutput
{
  // #region Properties
  /**
   * Property sub.
   *
   * Subject identifier - unique, stable identifier for the user.
   * This is the primary identifier for the user.
   *
   * @example a1b2c3d4-e5f6-7890-abcd-ef1234567890
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Subject identifier (unique user UUID)',
    readable: true,
    writable: false,
    required: true,
    identifier: true,
    example: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    openapiContext: [
      'type' => 'string',
      'format' => 'uuid',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uuid',
    ],
  )]
  public ?string $sub = null;

  /**
   * Property name.
   *
   * Full name of the user in displayable form.
   * Includes all name components.
   *
   * @example John Doe
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Full name of the user',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'John Doe',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'nullable' => true,
    ],
  )]
  public ?string $name = null;

  /**
   * Property givenName.
   *
   * Given name(s) or first name(s) of the user.
   *
   * @example John
   *
   * @since 1.0.0
   */
  #[SerializedName('given_name')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'First name of the user',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'John',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'nullable' => true,
    ],
  )]
  public ?string $givenName = null;

  /**
   * Property familyName.
   *
   * Surname(s) or last name(s) of the user.
   *
   * @example Doe
   *
   * @since 1.0.0
   */
  #[SerializedName('family_name')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Last name of the user',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'Doe',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'nullable' => true,
    ],
  )]
  public ?string $familyName = null;

  /**
   * Property preferredUsername.
   *
   * Shorthand name by which the user wishes to be referred to.
   * May be the same as the username.
   *
   * @example johndoe
   *
   * @since 1.0.0
   */
  #[SerializedName('preferred_username')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Preferred display name or username',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'johndoe',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'nullable' => true,
    ],
  )]
  public ?string $preferredUsername = null;

  /**
   * Property email.
   *
   * Email address of the user.
   * May be used for contact purposes.
   *
   * @example john.doe@example.com
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Email address of the user',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'john.doe@example.com',
    openapiContext: [
      'type' => 'string',
      'format' => 'email',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'email',
    ],
  )]
  public ?string $email = null;

  /**
   * Property emailVerified.
   *
   * True if the user's email address has been verified.
   *
   * @example true
   *
   * @since 1.0.0
   */
  #[SerializedName('email_verified')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Whether the email address has been verified',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: true,
    openapiContext: [
      'type' => 'boolean',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'boolean',
      'nullable' => true,
    ],
  )]
  public ?bool $emailVerified = null;

  /**
   * Property updatedAt.
   *
   * Unix timestamp of when the user's profile was last updated.
   *
   * @example 1733746800
   *
   * @since 1.0.0
   */
  #[SerializedName('updated_at')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Time when the user profile was last updated (Unix timestamp)',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 1733746800,
    openapiContext: [
      'type' => 'integer',
      'format' => 'int64',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'integer',
      'format' => 'int64',
    ],
  )]
  public ?int $updatedAt = null;
  // #endregion
}
