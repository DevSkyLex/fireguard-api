<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO OpenIdConfigurationOutput
 * @final
 *
 * Output data for OpenID Connect Discovery response (RFC 8414).
 * Contains metadata about the authorization server's configuration.
 * Returned by the GET /api/.well-known/openid-configuration endpoint.
 *
 * @category Output DTO
 * @package OAuth\Presentation\Api\Dto\Output
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OpenIdConfigurationOutput
{
  //#region Properties
  /**
   * Property issuer
   *
   * The identifier of the authorization server.
   * Must be a URL using HTTPS scheme.
   *
   * @example https://auth.example.com
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Issuer identifier URL of the authorization server',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'https://auth.example.com',
    openapiContext: [
      'type' => 'string',
      'format' => 'uri',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uri',
    ],
  )]
  public ?string $issuer = null;

  /**
   * Property authorizationEndpoint
   *
   * URL of the authorization endpoint.
   * Used to initiate the authorization code flow.
   *
   * @example https://auth.example.com/oauth2/authorize
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[SerializedName('authorization_endpoint')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'URL of the authorization endpoint',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'https://auth.example.com/oauth2/authorize',
    openapiContext: [
      'type' => 'string',
      'format' => 'uri',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uri',
    ],
  )]
  public ?string $authorizationEndpoint = null;

  /**
   * Property tokenEndpoint
   *
   * URL of the token endpoint.
   * Used to exchange authorization codes for tokens.
   *
   * @example https://auth.example.com/api/oauth2/token
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[SerializedName('token_endpoint')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'URL of the token endpoint',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'https://auth.example.com/api/oauth2/token',
    openapiContext: [
      'type' => 'string',
      'format' => 'uri',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uri',
    ],
  )]
  public ?string $tokenEndpoint = null;

  /**
   * Property userinfoEndpoint
   *
   * URL of the UserInfo endpoint.
   * Returns claims about the authenticated user.
   *
   * @example https://auth.example.com/api/oauth2/userinfo
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[SerializedName('userinfo_endpoint')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'URL of the UserInfo endpoint',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'https://auth.example.com/api/oauth2/userinfo',
    openapiContext: [
      'type' => 'string',
      'format' => 'uri',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uri',
    ],
  )]
  public ?string $userinfoEndpoint = null;

  /**
   * Property jwksUri
   *
   * URL of the JSON Web Key Set document.
   * Contains public keys for verifying JWT signatures.
   *
   * @example https://auth.example.com/api/.well-known/jwks.json
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[SerializedName('jwks_uri')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'URL of the JWKS endpoint',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'https://auth.example.com/api/.well-known/jwks.json',
    openapiContext: [
      'type' => 'string',
      'format' => 'uri',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uri',
    ],
  )]
  public ?string $jwksUri = null;

  /**
   * Property revocationEndpoint
   *
   * URL of the token revocation endpoint.
   * Used to invalidate access or refresh tokens.
   *
   * @example https://auth.example.com/api/oauth2/token/revoke
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[SerializedName('revocation_endpoint')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'URL of the token revocation endpoint',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'https://auth.example.com/api/oauth2/token/revoke',
    openapiContext: [
      'type' => 'string',
      'format' => 'uri',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uri',
    ],
  )]
  public ?string $revocationEndpoint = null;

  /**
   * Property introspectionEndpoint
   *
   * URL of the token introspection endpoint.
   * Used to validate and inspect tokens.
   *
   * @example https://auth.example.com/api/oauth2/token/introspect
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[SerializedName('introspection_endpoint')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'URL of the token introspection endpoint',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'https://auth.example.com/api/oauth2/token/introspect',
    openapiContext: [
      'type' => 'string',
      'format' => 'uri',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uri',
    ],
  )]
  public ?string $introspectionEndpoint = null;

  /**
   * Property endSessionEndpoint
   *
   * URL of the end session (logout) endpoint.
   * Used to terminate user sessions (OpenID Connect Session Management).
   *
   * @example https://auth.example.com/api/oauth2/logout
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[SerializedName('end_session_endpoint')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'URL of the end session (logout) endpoint',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'https://auth.example.com/api/oauth2/logout',
    openapiContext: [
      'type' => 'string',
      'format' => 'uri',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uri',
    ],
  )]
  public ?string $endSessionEndpoint = null;

  /**
   * Property scopesSupported
   *
   * List of OAuth2 scopes supported by the server.
   *
   * @example ["openid", "profile", "email", "offline_access"]
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>|null
   */
  #[SerializedName('scopes_supported')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'List of supported OAuth2 scopes',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: ['openid', 'profile', 'email', 'offline_access'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
    ],
  )]
  public ?array $scopesSupported = null;

  /**
   * Property responseTypesSupported
   *
   * List of OAuth2 response types supported.
   *
   * @example ["code", "token", "id_token"]
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>|null
   */
  #[SerializedName('response_types_supported')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'List of supported response types',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: ['code', 'token', 'id_token'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
    ],
  )]
  public ?array $responseTypesSupported = null;

  /**
   * Property grantTypesSupported
   *
   * List of OAuth2 grant types supported.
   *
   * @example ["authorization_code", "client_credentials", "refresh_token"]
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>|null
   */
  #[SerializedName('grant_types_supported')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'List of supported grant types',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: ['authorization_code', 'client_credentials', 'refresh_token'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
    ],
  )]
  public ?array $grantTypesSupported = null;

  /**
   * Property tokenEndpointAuthMethodsSupported
   *
   * List of client authentication methods supported at the token endpoint.
   *
   * @example ["client_secret_basic", "client_secret_post"]
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>|null
   */
  #[SerializedName('token_endpoint_auth_methods_supported')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Supported token endpoint authentication methods',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: ['client_secret_basic', 'client_secret_post'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
    ],
  )]
  public ?array $tokenEndpointAuthMethodsSupported = null;

  /**
   * Property codeChallengeMethodsSupported
   *
   * List of PKCE code challenge methods supported.
   *
   * @example ["S256", "plain"]
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>|null
   */
  #[SerializedName('code_challenge_methods_supported')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Supported PKCE code challenge methods',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: ['S256', 'plain'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
    ],
  )]
  public ?array $codeChallengeMethodsSupported = null;

  /**
   * Property subjectTypesSupported
   *
   * List of subject identifier types supported.
   *
   * @example ["public"]
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>|null
   */
  #[SerializedName('subject_types_supported')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Supported subject identifier types',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: ['public'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
    ],
  )]
  public ?array $subjectTypesSupported = null;

  /**
   * Property idTokenSigningAlgValuesSupported
   *
   * List of JWS signing algorithms supported for ID tokens.
   *
   * @example ["RS256"]
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>|null
   */
  #[SerializedName('id_token_signing_alg_values_supported')]
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Supported ID token signing algorithms',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: ['RS256'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
    ],
  )]
  public ?array $idTokenSigningAlgValuesSupported = null;
  //#endregion
}
