<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\Auth;

use ApiPlatform\Metadata\ApiProperty;
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};

/**
 * DTO LoginOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class LoginOutput
{
  // #region Properties
  /**
   * Property accessToken.
   *
   * JWT access token for authenticated API requests.
   * Use in Authorization header as: Bearer <token>
   *
   * @example eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
   *
   * @since 1.0.0
   */
  #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
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
   * Property tokenType.
   *
   * The type of token issued. Always 'Bearer'.
   * Indicates the token should be used as a Bearer token.
   *
   * @example Bearer
   *
   * @since 1.0.0
   */
  #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
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
   * Property expiresIn.
   *
   * Lifetime of the access token in seconds.
   * After this duration, the token will expire.
   *
   * @example 3600
   *
   * @since 1.0.0
   */
  #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
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
   * Property scope.
   *
   * Space-separated list of granted permissions.
   *
   * @example openid profile email
   *
   * @since 1.0.0
   */
  #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
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
   * Property mfaRequired.
   *
   * Indicates if MFA is required to complete authentication.
   */
  #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'If true, authentication is incomplete. Use mfa_token to verify code.',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: true,
    openapiContext: [
      'type' => 'boolean',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'boolean',
      'nullable' => true,
    ],
  )]
  #[SerializedName('mfa_required')]
  public ?bool $mfaRequired = null;

  /**
   * Property mfaToken.
   *
   * Pre-Auth Token to use for MFA verification.
   */
  #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Temporary Pre-Auth Token (JWT) covering the partial authentication state.',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'eyJ...',
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
  #[SerializedName('mfa_token')]
  public ?string $mfaToken = null;

  /**
   * Property challengeToken.
   *
   * The challenge token from the OTP system.
   */
  #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'The OTP challenge token (reference).',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'abc...',
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
  #[SerializedName('challenge_token')]
  public ?string $challengeToken = null;

  /**
   * Property mfaMethod.
   *
   * The method used to deliver the MFA code.
   */
  #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'The delivery method for the MFA code (email, sms, totp).',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'email',
    openapiContext: [
      'type' => 'string',
      'enum' => ['email', 'sms', 'totp'],
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'nullable' => true,
    ],
  )]
  #[SerializedName('mfa_method')]
  public ?string $mfaMethod = null;

  /**
   * Property mfaDestination.
   *
   * The masked destination where the MFA code was sent.
   */
  #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'The masked destination where the code was sent (e.g., "j***e@e****e.com" or "+336****5678").',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'j***e@e****e.com',
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
  #[SerializedName('mfa_destination')]
  public ?string $mfaDestination = null;

  /**
   * Property mfaResendIn.
   *
   * Seconds until MFA code can be resent.
   */
  #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
  #[ApiProperty(
    description: 'Seconds until MFA code can be resent',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 60,
    openapiContext: [
      'type' => 'integer',
      'nullable' => true,
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'integer',
      'nullable' => true,
    ],
  )]
  #[SerializedName('mfa_resend_in')]
  public ?int $mfaResendIn = null;

  // #endregion
}
