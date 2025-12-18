<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO TokenOutput
 * @final
 *
 * Output data for OAuth2 token issuance response (RFC 6749).
 * Contains the access token and optional refresh token.
 * Returned by the POST /api/oauth2/token endpoint.
 *
 * @category Output DTO
 * @package OAuth\Presentation\Api\Dto\Output
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenOutput
{
  //#region Properties
  /**
   * Property accessToken
   *
   * The access token issued by the authorization server.
   * Used to authenticate API requests via Bearer authentication.
   *
   * @example eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[SerializedName(serializedName: 'access_token')]
  #[ApiProperty(
    description: 'JWT access token for API authentication',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public ?string $accessToken = null;

  /**
   * Property tokenType
   *
   * The type of token issued. Always 'Bearer' for OAuth2.
   * Indicates how the token should be used in requests.
   *
   * @example Bearer
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[SerializedName(serializedName: 'token_type')]
  #[ApiProperty(
    description: 'Token type (always Bearer)',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'Bearer',
    openapiContext: [
      'type' => 'string',
      'enum' => ['Bearer'],
      'default' => 'Bearer',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'default' => 'Bearer',
    ],
  )]
  public ?string $tokenType = null;

  /**
   * Property expiresIn
   *
   * The lifetime of the access token in seconds.
   * After this time, the token will be invalid.
   *
   * @example 3600
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?int
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[SerializedName(serializedName: 'expires_in')]
  #[ApiProperty(
    description: 'Token lifetime in seconds',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 3600,
    openapiContext: [
      'type' => 'integer',
      'minimum' => 1,
      'example' => 3600,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'integer',
      'minimum' => 1,
    ],
  )]
  public ?int $expiresIn = null;

  /**
   * Property refreshToken
   *
   * The refresh token for obtaining new access tokens.
   * Only issued for certain grant types.
   * Has a longer lifetime than the access token.
   *
   * @example dGhpcyBpcyBhIHJlZnJlc2ggdG9rZW4...
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[SerializedName(serializedName: 'refresh_token')]
  #[ApiProperty(
    description: 'Refresh token for obtaining new access tokens (optional)',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'dGhpcyBpcyBhIHJlZnJlc2ggdG9rZW4...',
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
  public ?string $refreshToken = null;

  /**
   * Property scope
   *
   * The scopes granted for this access token.
   * Space-separated list of permissions.
   *
   * @example openid profile email
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[SerializedName(serializedName: 'scope')]
  #[ApiProperty(
    description: 'Space-separated list of granted scopes',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'openid profile email',
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
  public ?string $scope = null;
  //#endregion
}
