<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Authorization\Presentation\Api\Serialization\PermissionSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO PermissionInput
 * @final
 *
 * Input DTO for creating or updating a permission.
 * Used by POST /api/permissions and PATCH /api/permissions/{id} endpoints.
 *
 * @category Input DTO
 * @package Authorization\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PermissionInput
{
  //#region Properties
  /**
   * Property name
   * 
   * The permission name in format "resource.action".
   * Must follow the pattern: lowercase letters, underscores, or asterisks 
   * separated by a dot.
   * 
   * @example users.create
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  #[Groups([PermissionSerializationGroup::WRITE])]
  #[Assert\NotBlank(message: 'The permission name is required.')]
  #[Assert\Length(
    min: 3,
    max: 100,
    minMessage: 'Permission name must be at least {{ limit }} characters.',
    maxMessage: 'Permission name cannot exceed {{ limit }} characters.'
  )]
  #[Assert\Regex(
    pattern: '/^[a-z_*]+\.[a-z_*]+$/',
    message: 'Permission name must be in format "resource.action" (e.g., "users.create").'
  )]
  #[ApiProperty(
    description: 'Permission name in format "resource.action" (e.g., users.create, posts.delete)',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'users.create',
    openapiContext: [
      'type' => 'string',
      'pattern' => '^[a-z_*]+\.[a-z_*]+$',
      'minLength' => 3,
      'maxLength' => 100,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'pattern' => '^[a-z_*]+\.[a-z_*]+$',
      'minLength' => 3,
      'maxLength' => 100,
    ],
  )]
  public string $name = '';

  /**
   * Property description
   * 
   * Human-readable description explaining the permission's purpose.
   * 
   * @example Allows creating new user accounts in the system
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string|null
   */
  #[Groups([PermissionSerializationGroup::WRITE, PermissionSerializationGroup::UPDATE])]
  #[Assert\Length(
    max: 255,
    maxMessage: 'Description cannot exceed {{ limit }} characters.'
  )]
  #[ApiProperty(
    description: 'Human-readable description of the permission',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    default: null,
    example: 'Allows creating new user accounts in the system',
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
  //#endregion
}
