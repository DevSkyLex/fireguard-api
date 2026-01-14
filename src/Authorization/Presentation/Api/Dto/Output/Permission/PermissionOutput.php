<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Dto\Output\Permission;

use ApiPlatform\Metadata\ApiProperty;
use Authorization\Presentation\Api\Serialization\PermissionSerializationGroup;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};

/**
 * DTO PermissionOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PermissionOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * The unique identifier of the permission.
   *
   * @example 550e8400-e29b-41d4-a716-446655440000
   *
   * @since 1.0.0
   */
  #[Groups(groups: [PermissionSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Unique identifier of the permission (UUID)',
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
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uuid',
      'readOnly' => true,
    ],
  )]
  public string $id = '';

  /**
   * Property name.
   *
   * The permission name in format "resource.action".
   *
   * @example users.create
   *
   * @since 1.0.0
   */
  #[Groups(groups: [PermissionSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Permission name in format "resource.action" (e.g., users.create)',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'users.create',
    openapiContext: [
      'type' => 'string',
      'pattern' => '^[a-z_*]+\.[a-z_*]+$',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'pattern' => '^[a-z_*]+\.[a-z_*]+$',
      'readOnly' => true,
    ],
  )]
  public string $name = '';

  /**
   * Property description.
   *
   * The permission description explaining its purpose.
   *
   * @example Allows creating new user accounts
   *
   * @since 1.0.0
   */
  #[Groups(groups: [PermissionSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Human-readable description of the permission',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'Allows creating new user accounts',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'maxLength' => 255,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'nullable' => true,
      'maxLength' => 255,
    ],
  )]
  public ?string $description = null;

  /**
   * Property createdAt.
   *
   * When the permission was created.
   *
   * @example 2024-01-15T10:30:00+00:00
   *
   * @since 1.0.0
   */
  #[Groups(groups: [PermissionSerializationGroup::READ])]
  #[SerializedName('created_at')]
  #[ApiProperty(
    description: 'ISO 8601 datetime when the permission was created',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: '2024-01-15T10:30:00+00:00',
    openapiContext: [
      'type' => 'string',
      'format' => 'date-time',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'date-time',
      'nullable' => true,
    ],
  )]
  public ?string $createdAt = null;
  // #endregion
}
