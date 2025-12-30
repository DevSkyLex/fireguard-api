<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Dto\Input\Token;

use ApiPlatform\Metadata\ApiProperty;
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO TokenIntrospectionInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenIntrospectionInput
{
  // #region Properties
  /**
   * Property token.
   *
   * The token string to introspect.
   * Can be either an access token or a refresh token.
   *
   * @example eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The token field is required.')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_WRITE])]
  #[SerializedName(serializedName: 'token')]
  #[ApiProperty(
    description: 'The token string to introspect (access_token or refresh_token)',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...',
    openapiContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
  )]
  public ?string $token = null;

  /**
   * Property tokenTypeHint.
   *
   * A hint about the type of token being introspected.
   * Helps the server optimize the introspection process.
   * Optional but recommended for performance.
   *
   * @example access_token
   *
   * @since 1.0.0
   */
  #[Assert\Choice(
    choices: ['access_token', 'refresh_token'],
    message: 'Invalid token_type_hint. Allowed values: access_token, refresh_token.',
  )]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_WRITE])]
  #[SerializedName(serializedName: 'token_type_hint')]
  #[ApiProperty(
    description: 'Hint about the token type to optimize introspection (optional)',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    default: null,
    example: 'access_token',
    openapiContext: [
      'type' => 'string',
      'enum' => ['access_token', 'refresh_token'],
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'enum' => ['access_token', 'refresh_token'],
    ],
  )]
  public ?string $tokenTypeHint = null;
  // #endregion
}
