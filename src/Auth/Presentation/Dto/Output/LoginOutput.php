<?php

declare(strict_types=1);

namespace Auth\Presentation\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Auth\Presentation\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO LoginOutput
 * @final
 *
 * Output data for user authentication response.
 * Contains the access token for authenticated sessions.
 * Returned by the POST /api/auth/login endpoint.
 *
 * @category Output DTO
 * @package Auth\Presentation\Dto\Output
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class LoginOutput
{
  //#region Properties
  /**
   * Property accessToken
   *
   * JWT access token for authenticated API requests.
   * Use in Authorization header as: Bearer <token>
   *
   * @example eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[SerializedName('access_token')]
  #[ApiProperty(
    description: 'JWT access token for API authentication',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public ?string $accessToken = null;

  /**
   * Property tokenType
   *
   * The type of token issued. Always 'Bearer'.
   * Indicates the token should be used as a Bearer token.
   *
   * @example Bearer
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[SerializedName('token_type')]
  #[ApiProperty(
    description: 'Token type (always Bearer)',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'Bearer',
    openapiContext: [
      'type' => 'string',
      'enum' => ['Bearer'],
      'default' => 'Bearer',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'default' => 'Bearer',
    ],
  )]
  public string $tokenType = 'Bearer';

  /**
   * Property expiresIn
   *
   * Lifetime of the access token in seconds.
   * After this duration, the token will expire.
   *
   * @example 3600
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?int
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[SerializedName('expires_in')]
  #[ApiProperty(
    description: 'Token lifetime in seconds',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 3600,
    openapiContext: [
      'type' => 'integer',
      'minimum' => 1,
      'example' => 3600,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'integer',
      'minimum' => 1,
    ],
  )]
  public ?int $expiresIn = null;

  /**
   * Property scope
   *
   * Space-separated list of granted permissions.
   *
   * @example openid profile email
   *
   * @access public
   * @since 1.0.0
   *
   * @var ?string
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Space-separated list of granted scopes',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'openid profile email',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'nullable' => true,
    ],
  )]
  public ?string $scope = null;

  /**
   * Property mfaRequired
   *
   * Indicates if MFA is required to complete authentication.
   *
   * @var ?bool
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[ApiProperty(
    description: 'If true, authentication is incomplete. Use mfa_token to verify code.',
    example: true,
  )]
  #[SerializedName('mfa_required')]
  public ?bool $mfaRequired = null;

  /**
   * Property mfaToken
   *
   * Pre-Auth Token to use for MFA verification.
   *
   * @var ?string
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Temporary Pre-Auth Token (JWT) covering the partial authentication state.',
    example: 'eyJ...',
  )]
  #[SerializedName('mfa_token')]
  public ?string $mfaToken = null;

  /**
   * Property challengeToken
   *
   * The challenge token from the OTP system.
   *
   * @var ?string
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  #[ApiProperty(
    description: 'The OTP challenge token (reference).',
    example: 'abc...',
  )]
  #[SerializedName('challenge_token')]
  public ?string $challengeToken = null;

  //#endregion
}
