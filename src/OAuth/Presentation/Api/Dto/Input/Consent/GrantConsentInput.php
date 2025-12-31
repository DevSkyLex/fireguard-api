<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Dto\Input\Consent;

use ApiPlatform\Metadata\ApiProperty;
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO GrantConsentInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GrantConsentInput
{
  // #region Properties
  /**
   * Property clientId.
   *
   * The OAuth2 client identifier requesting consent.
   *
   * @example 01234567-89ab-cdef-0123-456789abcdef
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The client_id field is required.')]
  #[Assert\Uuid(message: 'The client_id must be a valid UUID.')]
  #[Groups(groups: [OAuthSerializationGroup::CONSENT_WRITE])]
  #[SerializedName(serializedName: 'client_id')]
  #[ApiProperty(
    description: 'OAuth2 client identifier (UUID)',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: '01234567-89ab-cdef-0123-456789abcdef',
    openapiContext: [
      'type' => 'string',
      'format' => 'uuid',
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uuid',
    ],
  )]
  public ?string $clientId = null;

  /**
   * Property responseType.
   *
   * The OAuth2 response type.
   *
   * @example code
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The response_type field is required.')]
  #[Assert\Choice(choices: ['code'], message: 'Invalid response_type. Allowed value: code.')]
  #[Groups(groups: [OAuthSerializationGroup::CONSENT_WRITE])]
  #[SerializedName(serializedName: 'response_type')]
  #[ApiProperty(
    description: 'OAuth2 response type (code)',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 'code',
    openapiContext: [
      'type' => 'string',
      'enum' => ['code'],
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'enum' => ['code'],
    ],
  )]
  public ?string $responseType = null;

  /**
   * Property redirectUri.
   *
   * The redirect URI to send the authorization code to.
   *
   * @example https://app.example.com/callback
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The redirect_uri field is required.')]
  #[Assert\Url(message: 'The redirect_uri must be a valid URL.')]
  #[Groups(groups: [OAuthSerializationGroup::CONSENT_WRITE])]
  #[SerializedName(serializedName: 'redirect_uri')]
  #[ApiProperty(
    description: 'Redirect URI registered for the client',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 'https://app.example.com/callback',
    openapiContext: [
      'type' => 'string',
      'format' => 'uri',
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'uri',
    ],
  )]
  public ?string $redirectUri = null;

  /**
   * Property scope.
   *
   * Space-separated list of requested scopes.
   *
   * @example openid profile email
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::CONSENT_WRITE])]
  #[SerializedName(serializedName: 'scope')]
  #[ApiProperty(
    description: 'Space-separated list of requested scopes',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    example: 'openid profile email',
    openapiContext: [
      'type' => 'string',
    ],
    jsonSchemaContext: [
      'type' => 'string',
    ],
  )]
  public ?string $scope = null;

  /**
   * Property state.
   *
   * Opaque value to maintain state between request and callback.
   *
   * @example 98e8937d
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::CONSENT_WRITE])]
  #[SerializedName(serializedName: 'state')]
  #[ApiProperty(
    description: 'Opaque state value returned to the client',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    example: '98e8937d',
    openapiContext: [
      'type' => 'string',
    ],
    jsonSchemaContext: [
      'type' => 'string',
    ],
  )]
  public ?string $state = null;

  /**
   * Property codeChallenge.
   *
   * PKCE code challenge (required for authorization_code).
   *
   * @example dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The code_challenge field is required for PKCE.')]
  #[Groups(groups: [OAuthSerializationGroup::CONSENT_WRITE])]
  #[SerializedName(serializedName: 'code_challenge')]
  #[ApiProperty(
    description: 'PKCE code challenge for authorization code flow',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk',
    openapiContext: [
      'type' => 'string',
    ],
    jsonSchemaContext: [
      'type' => 'string',
    ],
  )]
  public ?string $codeChallenge = null;

  /**
   * Property codeChallengeMethod.
   *
   * PKCE code challenge method.
   *
   * @example S256
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The code_challenge_method field is required for PKCE.')]
  #[Assert\Choice(choices: ['S256', 'plain'], message: 'Invalid code_challenge_method. Allowed values: S256, plain.')]
  #[Groups(groups: [OAuthSerializationGroup::CONSENT_WRITE])]
  #[SerializedName(serializedName: 'code_challenge_method')]
  #[ApiProperty(
    description: 'PKCE code challenge method (S256 or plain)',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 'S256',
    openapiContext: [
      'type' => 'string',
      'enum' => ['S256', 'plain'],
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'enum' => ['S256', 'plain'],
    ],
  )]
  public ?string $codeChallengeMethod = null;

  /**
   * Property nonce.
   *
   * Optional OIDC nonce value.
   *
   * @example n-0S6_WzA2Mj
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::CONSENT_WRITE])]
  #[SerializedName(serializedName: 'nonce')]
  #[ApiProperty(
    description: 'OIDC nonce value (optional)',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    example: 'n-0S6_WzA2Mj',
    openapiContext: [
      'type' => 'string',
    ],
    jsonSchemaContext: [
      'type' => 'string',
    ],
  )]
  public ?string $nonce = null;

  /**
   * Property approved.
   *
   * Whether the user approves the consent.
   *
   * @example true
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::CONSENT_WRITE])]
  #[SerializedName(serializedName: 'approved')]
  #[ApiProperty(
    description: 'Whether the user approves the consent',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    example: true,
    openapiContext: [
      'type' => 'boolean',
      'default' => true,
    ],
    jsonSchemaContext: [
      'type' => 'boolean',
      'default' => true,
    ],
  )]
  public ?bool $approved = true;
  // #endregion
}
