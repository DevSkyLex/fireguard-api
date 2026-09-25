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
  private const string SUCCESS_MESSAGE = 'Logged out successfully';

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
    example: self::SUCCESS_MESSAGE,
    openapiContext: [
      'type' => 'string',
      'default' => self::SUCCESS_MESSAGE,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'default' => self::SUCCESS_MESSAGE,
    ],
  )]
  public string $message = self::SUCCESS_MESSAGE;
  // #endregion
}
