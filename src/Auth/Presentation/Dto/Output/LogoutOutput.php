<?php

declare(strict_types=1);

namespace Auth\Presentation\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO LogoutOutput
 * @final
 *
 * Output data for logout response.
 * Confirms successful session termination.
 * Returned by the POST /api/auth/logout endpoint.
 *
 * @category Output DTO
 * @package Auth\Presentation\Dto\Output
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class LogoutOutput
{
  //#region Properties
  /**
   * Property message
   *
   * Human-readable confirmation message.
   * Indicates the logout was successful.
   *
   * @example Logged out successfully
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  #[Groups(groups: [OAuthSerializationGroup::TOKEN_READ])]
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
  //#endregion
}
