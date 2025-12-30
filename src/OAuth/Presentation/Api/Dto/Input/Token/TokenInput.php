<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Dto\Input\Token;

use ApiPlatform\Metadata\ApiProperty;
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use OAuth\Presentation\Api\Validator\GrantTypeRequirements\GrantTypeRequirements;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO TokenInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[GrantTypeRequirements]
final class TokenInput
{
  // #region Properties
  /**
   * Property grantType.
   *
   * The OAuth2 grant type to use for token issuance.
   * Determines which authentication flow is used.
   *
   * @example client_credentials
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The grant_type field is required.')]
  #[Assert\Choice(
    choices: ['client_credentials', 'refresh_token', 'authorization_code'],
    message: 'Invalid grant_type. Allowed values: client_credentials, refresh_token, authorization_code.',
  )]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_WRITE])]
  #[SerializedName(serializedName: 'grant_type')]
  #[ApiProperty(
    description: 'OAuth2 grant type (client_credentials, refresh_token, authorization_code)',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'client_credentials',
    openapiContext: [
      'type' => 'string',
      'enum' => ['client_credentials', 'refresh_token', 'authorization_code'],
      'example' => 'client_credentials',
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'enum' => ['client_credentials', 'refresh_token', 'authorization_code'],
    ],
  )]
  public ?string $grantType = null;

  /**
   * Property clientId.
   *
   * The unique identifier of the OAuth2 client application.
   * Must be a valid UUID registered in the system.
   *
   * @example 01234567-89ab-cdef-0123-456789abcdef
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Uuid(message: 'The client_id must be a valid UUID.')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_WRITE])]
  #[SerializedName(serializedName: 'client_id')]
  #[ApiProperty(
    description: 'Unique identifier of the OAuth2 client application (UUID)',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: '01234567-89ab-cdef-0123-456789abcdef',
    openapiContext: [
      'type' => 'string',
      'format' => 'uuid',
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uuid',
    ],
  )]
  public ?string $clientId = null;

  /**
   * Property clientSecret.
   *
   * The secret key associated with the OAuth2 client.
   * Used for client authentication. Write-only, never exposed.
   *
   * @example your-client-secret
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_WRITE])]
  #[SerializedName(serializedName: 'client_secret')]
  #[ApiProperty(
    description: 'Secret key for client authentication (write-only)',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'your-client-secret',
    openapiContext: [
      'type' => 'string',
      'format' => 'password',
      'writeOnly' => true,
      'minLength' => 32,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
  )]
  public ?string $clientSecret = null;

  /**
   * Property scope.
   *
   * Space-separated list of OAuth2 scopes requested.
   * Defines the access permissions for the token.
   *
   * @example openid profile email
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_WRITE])]
  #[SerializedName(serializedName: 'scope')]
  #[ApiProperty(
    description: 'Space-separated list of requested OAuth2 scopes',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    default: null,
    example: 'openid profile email',
    openapiContext: [
      'type' => 'string',
      'pattern' => '^[a-zA-Z0-9_]+(\s+[a-zA-Z0-9_]+)*$',
      'example' => 'openid profile email',
    ],
    jsonSchemaContext: [
      'type' => 'string',
    ],
  )]
  public ?string $scope = null;

  /**
   * Property refreshToken.
   *
   * The refresh token to exchange for a new access token.
   * Required when grant_type is 'refresh_token'.
   *
   * @example dGhpcyBpcyBhIHJlZnJlc2ggdG9rZW4...
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_WRITE])]
  #[SerializedName(serializedName: 'refresh_token')]
  #[ApiProperty(
    description: 'Refresh token for token renewal (required for refresh_token grant)',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    default: null,
    example: 'dGhpcyBpcyBhIHJlZnJlc2ggdG9rZW4...',
    openapiContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
  )]
  public ?string $refreshToken = null;

  /**
   * Property code.
   *
   * The authorization code received from the authorization endpoint.
   * Required when grant_type is 'authorization_code'.
   *
   * @example authorization-code-value
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_WRITE])]
  #[SerializedName(serializedName: 'code')]
  #[ApiProperty(
    description: 'Authorization code from /authorize endpoint (required for authorization_code grant)',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    default: null,
    example: 'authorization-code-value',
    openapiContext: [
      'type' => 'string',
    ],
    jsonSchemaContext: [
      'type' => 'string',
    ],
  )]
  public ?string $code = null;

  /**
   * Property redirectUri.
   *
   * The redirect URI used in the authorization request.
   * Must match the URI registered for the client.
   * Required when grant_type is 'authorization_code'.
   *
   * @example https://app.example.com/callback
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_WRITE])]
  #[SerializedName(serializedName: 'redirect_uri')]
  #[ApiProperty(
    description: 'Redirect URI matching the authorization request (required for authorization_code grant)',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    default: null,
    example: 'https://app.example.com/callback',
    openapiContext: [
      'type' => 'string',
      'format' => 'uri',
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uri',
    ],
  )]
  public ?string $redirectUri = null;

  /**
   * Property codeVerifier.
   *
   * The PKCE code verifier used for Proof Key for Code Exchange.
   * Must match the code_challenge sent to the authorization endpoint.
   * Required when grant_type is 'authorization_code' with PKCE.
   *
   * @example dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_WRITE])]
  #[SerializedName(serializedName: 'code_verifier')]
  #[ApiProperty(
    description: 'PKCE code verifier for enhanced security (required for authorization_code with PKCE)',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    default: null,
    example: 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk',
    openapiContext: [
      'type' => 'string',
      'minLength' => 43,
      'maxLength' => 128,
      'pattern' => '^[A-Za-z0-9_-]+$',
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'minLength' => 43,
      'maxLength' => 128,
    ],
  )]
  public ?string $codeVerifier = null;
  // #endregion
}
