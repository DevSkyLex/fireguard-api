<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Authorization\Presentation\Api\Serialization\RoleSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO RoleInput
 * @final
 *
 * Input DTO for creating or updating a role.
 * Used by POST /api/roles and PATCH /api/roles/{id} endpoints.
 *
 * @category Input DTO
 * @package Authorization\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RoleInput
{
  //#region Properties
  /**
   * Property name
   * 
   * The role name. Must start with a lowercase letter and contain 
   * only lowercase letters, numbers, and underscores.
   * 
   * @example moderator
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  #[Groups(groups: [RoleSerializationGroup::WRITE])]
  #[Assert\NotBlank(message: 'The role name is required.')]
  #[Assert\Length(
    min: 2,
    max: 50,
    minMessage: 'Role name must be at least {{ limit }} characters.',
    maxMessage: 'Role name cannot exceed {{ limit }} characters.'
  )]
  #[Assert\Regex(
    pattern: '/^[a-z][a-z0-9_]*$/',
    message: 'Role name must start with a letter and contain only lowercase letters, numbers, and underscores.'
  )]
  #[ApiProperty(
    description: 'Role name (lowercase, starts with letter, may contain numbers and underscores)',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'moderator',
    openapiContext: [
      'type' => 'string',
      'pattern' => '^[a-z][a-z0-9_]*$',
      'minLength' => 2,
      'maxLength' => 50,
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
   * Property description
   * 
   * Human-readable description explaining the role's purpose and access level.
   * 
   * @example Moderator role with content management permissions
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string|null
   */
  #[Groups(groups: [RoleSerializationGroup::WRITE, RoleSerializationGroup::UPDATE])]
  #[Assert\Length(
    max: 255,
    maxMessage: 'Description cannot exceed {{ limit }} characters.'
  )]
  #[ApiProperty(
    description: 'Human-readable description of the role',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    default: null,
    example: 'Moderator role with content management permissions',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'maxLength' => 255,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'nullable' => true,
      'maxLength' => 255,
    ],
  )]
  public ?string $description = null;

  /**
   * Property isSystem
   * 
   * Whether this is a system role. System roles cannot be deleted and 
   * are protected from accidental removal.
   * 
   * @example false
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var bool
   */
  #[Groups(groups: [RoleSerializationGroup::WRITE])]
  #[SerializedName('is_system')]
  #[ApiProperty(
    description: 'Mark as system role (protected from deletion)',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    default: false,
    example: false,
    openapiContext: [
      'type' => 'boolean',
      'default' => false,
    ],
    jsonSchemaContext: [
      'type' => 'boolean',
      'default' => false,
    ],
  )]
  public bool $isSystem = false;

  /**
   * Property permissionIds
   * 
   * List of permission UUIDs to assign to this role.
   * 
   * @example ["550e8400-e29b-41d4-a716-446655440000", "550e8400-e29b-41d4-a716-446655440001"]
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var array<string>
   */
  #[Groups(groups: [RoleSerializationGroup::WRITE, RoleSerializationGroup::UPDATE])]
  #[SerializedName('permission_ids')]
  #[ApiProperty(
    description: 'List of permission UUIDs to assign to this role',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    example: ['550e8400-e29b-41d4-a716-446655440000'],
    openapiContext: [
      'type' => 'array',
      'items' => [
        'type' => 'string',
        'format' => 'uuid',
      ],
      'default' => [],
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => [
        'type' => 'string',
        'format' => 'uuid',
      ],
      'default' => [],
    ],
  )]
  public array $permissionIds = [];
  //#endregion
}
