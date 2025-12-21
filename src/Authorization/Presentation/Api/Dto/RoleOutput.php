<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Authorization\Presentation\Api\Serialization\RoleSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO RoleOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RoleOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * The unique identifier of the role.
   *
   * @example 550e8400-e29b-41d4-a716-446655440000
   *
   * @since 1.0.0
   */
  #[Groups(groups: [RoleSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Unique identifier of the role (UUID)',
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
   * The role name (lowercase with underscores).
   *
   * @example admin
   *
   * @since 1.0.0
   */
  #[Groups(groups: [RoleSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Role name (lowercase letters, numbers, and underscores)',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'admin',
    openapiContext: [
      'type' => 'string',
      'pattern' => '^[a-z][a-z0-9_]*$',
      'minLength' => 2,
      'maxLength' => 50,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'pattern' => '^[a-z][a-z0-9_]*$',
      'minLength' => 2,
      'maxLength' => 50,
    ],
  )]
  public string $name = '';

  /**
   * Property description.
   *
   * The role description explaining its purpose.
   *
   * @example Administrator role with full system access
   *
   * @since 1.0.0
   */
  #[Groups(groups: [RoleSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Human-readable description of the role',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'Administrator role with full system access',
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
   * Property isSystem.
   *
   * Whether this is a system role (cannot be deleted).
   *
   * @example false
   *
   * @since 1.0.0
   */
  #[Groups(groups: [RoleSerializationGroup::READ])]
  #[SerializedName('is_system')]
  #[ApiProperty(
    description: 'Indicates if this is a system role (protected from deletion)',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: false,
    openapiContext: [
      'type' => 'boolean',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'boolean',
    ],
  )]
  public bool $isSystem = false;

  /**
   * Property permissions.
   *
   * The permissions assigned to this role.
   *
   * @since 1.0.0
   *
   * @var array<PermissionOutput>
   */
  #[Groups(groups: [RoleSerializationGroup::READ])]
  #[ApiProperty(
    description: 'List of permissions assigned to this role',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    openapiContext: [
      'type' => 'array',
      'items' => [
        'type' => 'object',
        'properties' => [
          'id' => ['type' => 'string', 'format' => 'uuid'],
          'name' => ['type' => 'string'],
          'description' => ['type' => 'string', 'nullable' => true],
          'created_at' => ['type' => 'string', 'format' => 'date-time'],
        ],
      ],
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => [
        'type' => 'object',
      ],
    ],
  )]
  public array $permissions = [];

  /**
   * Property createdAt.
   *
   * When the role was created.
   *
   * @example 2024-01-15T10:30:00+00:00
   *
   * @since 1.0.0
   */
  #[Groups(groups: [RoleSerializationGroup::READ])]
  #[SerializedName('created_at')]
  #[ApiProperty(
    description: 'ISO 8601 datetime when the role was created',
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
