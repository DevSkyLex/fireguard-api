<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO TokenIntrospectionOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenIntrospectionOutput
{
  // #region Properties
  /**
   * Property active.
   *
   * Boolean indicator of whether the token is currently active.
   * A token is active if it has not expired and has not been revoked.
   *
   * @example true
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Whether the token is currently active (not expired, not revoked)',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: true,
    openapiContext: [
      'type' => 'boolean',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'boolean',
    ],
  )]
  public bool $active = false;

  /**
   * Property scope.
   *
   * Space-separated list of scopes associated with the token.
   *
   * @example openid profile email
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
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

  /**
   * Property clientId.
   *
   * The client identifier for the OAuth2 client that requested the token.
   *
   * @example 01234567-89ab-cdef-0123-456789abcdef
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[SerializedName(serializedName: 'client_id')]
  #[ApiProperty(
    description: 'Client identifier that requested the token',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: '01234567-89ab-cdef-0123-456789abcdef',
    openapiContext: [
      'type' => 'string',
      'format' => 'uuid',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uuid',
    ],
  )]
  public ?string $clientId = null;

  /**
   * Property username.
   *
   * Human-readable identifier for the resource owner.
   * Typically the user's email address.
   *
   * @example john.doe@example.com
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Human-readable identifier (email) of the resource owner',
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
  public ?string $username = null;

  /**
   * Property tokenType.
   *
   * The type of the token. Either 'Bearer' for access tokens
   * or 'refresh_token' for refresh tokens.
   *
   * @example Bearer
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[SerializedName(serializedName: 'token_type')]
  #[ApiProperty(
    description: 'Type of the token (Bearer or refresh_token)',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'Bearer',
    openapiContext: [
      'type' => 'string',
      'enum' => ['Bearer', 'refresh_token'],
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'enum' => ['Bearer', 'refresh_token'],
    ],
  )]
  public ?string $tokenType = null;

  /**
   * Property exp.
   *
   * Unix timestamp indicating when the token expires.
   *
   * @example 1733750400
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Token expiration time (Unix timestamp)',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 1733750400,
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
  public ?int $exp = null;

  /**
   * Property iat.
   *
   * Unix timestamp indicating when the token was issued.
   *
   * @example 1733746800
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Token issue time (Unix timestamp)',
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
  public ?int $iat = null;

  /**
   * Property nbf.
   *
   * Unix timestamp indicating when the token becomes valid (not before).
   *
   * @example 1733746800
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Token not-before time (Unix timestamp)',
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
  public ?int $nbf = null;

  /**
   * Property sub.
   *
   * Subject identifier - the unique identifier for the user.
   *
   * @example a1b2c3d4-e5f6-7890-abcd-ef1234567890
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Subject identifier (user UUID)',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    openapiContext: [
      'type' => 'string',
      'format' => 'uuid',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uuid',
    ],
  )]
  public ?string $sub = null;

  /**
   * Property aud.
   *
   * Audience - the intended recipients of the token.
   *
   * @example 01234567-89ab-cdef-0123-456789abcdef
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Intended audience for the token',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: '01234567-89ab-cdef-0123-456789abcdef',
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
  public ?string $aud = null;

  /**
   * Property iss.
   *
   * Issuer - the authorization server that issued the token.
   *
   * @example https://auth.example.com
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Issuer URL of the authorization server',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'https://auth.example.com',
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
  public ?string $iss = null;

  /**
   * Property jti.
   *
   * JWT ID - unique identifier for the token.
   *
   * @example unique-token-identifier-123
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Unique JWT identifier',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'unique-token-identifier-123',
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
  public ?string $jti = null;
  // #endregion
}
