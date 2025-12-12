<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO AddPermissionInput
 * @final
 *
 * Input DTO for adding a permission to a role.
 * Used by POST /api/roles/{id}/permissions endpoint.
 *
 * @category Input DTO
 * @package Authorization\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AddPermissionInput
{
  //#region Properties
  /**
   * Property permissionId
   * 
   * The UUID of the permission to add to the role.
   * 
   * @example 550e8400-e29b-41d4-a716-446655440000
   * 
   * @access public
   * @since 1.0.0
   * 
   * @var string
   */
  #[Assert\NotBlank(message: 'The permission ID is required.')]
  #[Assert\Uuid(message: 'The permission ID must be a valid UUID.')]
  #[SerializedName('permission_id')]
  #[ApiProperty(
    description: 'UUID of the permission to add to the role',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: '550e8400-e29b-41d4-a716-446655440000',
    openapiContext: [
      'type' => 'string',
      'format' => 'uuid',
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uuid',
    ],
  )]
  public string $permissionId = '';
  //#endregion
}
