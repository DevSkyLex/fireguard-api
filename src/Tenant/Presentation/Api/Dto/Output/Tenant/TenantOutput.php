<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Dto\Output\Tenant;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Tenant\Presentation\Api\Serialization\TenantSerializationGroup;

/**
 * DTO TenantOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TenantOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * The tenant ID.
   */
  #[Groups([TenantSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Tenant identifier (UUID).',
    readable: true,
    writable: false,
    required: true,
    identifier: true,
    example: '550e8400-e29b-41d4-a716-446655440000',
    openapiContext: [
      'type' => 'string',
      'format' => 'uuid',
      'readOnly' => true,
    ],
  )]
  public string $id = '';

  /**
   * Property name.
   *
   * The tenant name.
   */
  #[Groups([TenantSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Tenant name.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'Acme Corp',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $name = '';

  /**
   * Property isActive.
   *
   * Whether the tenant is active.
   */
  #[Groups([TenantSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Whether the tenant is active.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: true,
    openapiContext: [
      'type' => 'boolean',
      'readOnly' => true,
    ],
  )]
  public bool $isActive = true;

  /**
   * Property accessTokenTtl.
   *
   * Access token TTL in seconds.
   */
  #[Groups([TenantSerializationGroup::READ, TenantSerializationGroup::SETTINGS])]
  #[ApiProperty(
    description: 'Access token TTL in seconds.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 3600,
    openapiContext: [
      'type' => 'integer',
      'readOnly' => true,
    ],
  )]
  public int $accessTokenTtl = 3600;

  /**
   * Property refreshTokenTtl.
   *
   * Refresh token TTL in seconds.
   */
  #[Groups([TenantSerializationGroup::READ, TenantSerializationGroup::SETTINGS])]
  #[ApiProperty(
    description: 'Refresh token TTL in seconds.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 86400,
    openapiContext: [
      'type' => 'integer',
      'readOnly' => true,
    ],
  )]
  public int $refreshTokenTtl = 86400;

  /**
   * Property requirePkce.
   *
   * Whether PKCE is required.
   */
  #[Groups([TenantSerializationGroup::READ, TenantSerializationGroup::SETTINGS])]
  #[ApiProperty(
    description: 'Whether PKCE is required for OAuth2 flows.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: true,
    openapiContext: [
      'type' => 'boolean',
      'readOnly' => true,
    ],
  )]
  public bool $requirePkce = true;

  /**
   * Property createdAt.
   *
   * The creation timestamp.
   */
  #[Groups([TenantSerializationGroup::READ])]
  #[ApiProperty(
    description: 'ISO 8601 datetime when the tenant was created.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: '2026-01-29T10:15:30+00:00',
    openapiContext: [
      'type' => 'string',
      'format' => 'date-time',
      'readOnly' => true,
    ],
  )]
  public string $createdAt = '';
  // #endregion
}
