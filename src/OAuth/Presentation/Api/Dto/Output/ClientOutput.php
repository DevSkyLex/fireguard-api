<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO ClientOutput
 * @final
 *
 * DTO for Client Output (Read).
 *
 * @category DTO
 * @package OAuth\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientOutput
{
  //#region Properties
  /**
   * Property id
   *
   * The client ID.
   * 
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [OAuthSerializationGroup::CLIENT_READ])]
  #[SerializedName('client_id')]
  #[ApiProperty(
    description: 'Unique client identifier (UUID)',
    readable: true,
    writable: false,
    required: true,
    identifier: true,
    example: '01234567-89ab-cdef-0123-456789abcdef',
    openapiContext: ['type' => 'string', 'format' => 'uuid', 'readOnly' => true],
    jsonSchemaContext: ['type' => 'string', 'format' => 'uuid']
  )]
  public ?string $id = null;

  /**
   * Property name
   *
   * The client name.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [OAuthSerializationGroup::CLIENT_READ])]
  #[SerializedName('client_name')]
  #[ApiProperty(
    description: 'Human-readable name of the client',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'My Awesome App',
    openapiContext: ['type' => 'string', 'readOnly' => true],
    jsonSchemaContext: ['type' => 'string']
  )]
  public ?string $name = null;

  /**
   * Property secret
   *
   * The client secret (only visible once upon 
   * creation/regeneration).
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [OAuthSerializationGroup::CLIENT_SECRET])]
  #[SerializedName('client_secret')]
  #[ApiProperty(
    description: 'Client secret (only shown once)',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'your-client-secret',
    openapiContext: ['type' => 'string', 'readOnly' => true],
    jsonSchemaContext: ['type' => 'string']
  )]
  public ?string $secret = null;

  /**
   * Property redirectUris
   *
   * The allowed redirect URIs.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Groups(groups: [OAuthSerializationGroup::CLIENT_READ])]
  #[SerializedName('redirect_uris')]
  #[ApiProperty(
    description: 'List of registered redirect URIs',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: ['https://app.example.com/callback'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string', 'format' => 'uri'],
      'readOnly' => true
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string', 'format' => 'uri']
    ]
  )]
  public array $redirectUris = [];

  /**
   * Property grantTypes
   *
   * The allowed grant types.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Groups(groups: [OAuthSerializationGroup::CLIENT_READ])]
  #[SerializedName('grant_types')]
  #[ApiProperty(
    description: 'List of allowed grant types',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: ['authorization_code'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
      'readOnly' => true
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string']
    ]
  )]
  public array $grantTypes = [];

  /**
   * Property scopes
   *
   * The allowed scopes.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Groups(groups: [OAuthSerializationGroup::CLIENT_READ])]
  #[SerializedName('scopes')]
  #[ApiProperty(
    description: 'List of allowed scopes',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: ['openid', 'profile'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
      'readOnly' => true
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string']
    ]
  )]
  public array $scopes = [];

  /**
   * Property isActive
   *
   * Whether the client is active.
   *
   * @access public
   * @since 1.0.0
   *
   * @var bool
   */
  #[Groups(groups: [OAuthSerializationGroup::CLIENT_READ])]
  #[SerializedName('is_active')]
  #[ApiProperty(
    description: 'Whether the client is currently active',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: true,
    openapiContext: ['type' => 'boolean', 'readOnly' => true],
    jsonSchemaContext: ['type' => 'boolean']
  )]
  public bool $isActive = true;

  /**
   * Property createdAt
   *
   * The creation timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[Groups(groups: [OAuthSerializationGroup::CLIENT_READ])]
  #[SerializedName('created_at')]
  #[ApiProperty(
    description: 'Creation timestamp (ISO 8601)',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: '2025-01-01T12:00:00+00:00',
    openapiContext: ['type' => 'string', 'format' => 'date-time', 'readOnly' => true],
    jsonSchemaContext: ['type' => 'string', 'format' => 'date-time']
  )]
  public ?string $createdAt = null;
}
