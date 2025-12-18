<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO TokenRevocationInput
 * @final
 *
 * Input data for OAuth2 token revocation (RFC 7009).
 * Allows clients to invalidate tokens when no longer needed.
 * Used by the POST /api/oauth2/token/revoke endpoint.
 *
 * @category Input DTO
 * @package OAuth\Presentation\Api\Dto\Input
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TokenRevocationInput
{
  //#region Properties
  /**
   * Property token
   *
   * The token string to revoke.
   * Can be either an access token or a refresh token.
   * After revocation, the token will no longer be valid.
   *
   * @example eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[Assert\NotBlank(message: 'The token field is required.')]
  #[SerializedName(serializedName: 'token')]
  #[ApiProperty(
    description: 'The token string to revoke (access_token or refresh_token)',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...',
    openapiContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
  )]
  public ?string $token = null;

  /**
   * Property tokenTypeHint
   *
   * A hint about the type of token being revoked.
   * Helps the server locate the token more efficiently.
   * If the hint is wrong, the server will still attempt revocation.
   *
   * @example access_token
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[Assert\Choice(
    choices: ['access_token', 'refresh_token'],
    message: 'Invalid token_type_hint. Allowed values: access_token, refresh_token.'
  )]
  #[SerializedName(serializedName: 'token_type_hint')]
  #[ApiProperty(
    description: 'Hint about the token type to optimize revocation (optional)',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    default: null,
    example: 'access_token',
    openapiContext: [
      'type' => 'string',
      'enum' => ['access_token', 'refresh_token'],
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'enum' => ['access_token', 'refresh_token'],
    ],
  )]
  public ?string $tokenTypeHint = null;
  //#endregion
}
