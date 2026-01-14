<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\Auth;

use ApiPlatform\Metadata\ApiProperty;
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO LogoutOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class LogoutOutput
{
  // #region Properties
  /**
   * Property message.
   *
   * Human-readable confirmation message.
   * Indicates the logout was successful.
   *
   * @example Logged out successfully
   *
   * @since 1.0.0
   */
  #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Confirmation message indicating successful logout',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'Logged out successfully',
    openapiContext: [
      'type' => 'string',
      'default' => 'Logged out successfully',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'default' => 'Logged out successfully',
    ],
  )]
  public string $message = 'Logged out successfully';
  // #endregion
}
