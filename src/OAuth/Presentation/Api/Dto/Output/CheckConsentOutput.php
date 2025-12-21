<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use OAuth\Presentation\Api\Serialization\OAuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * DTO CheckConsentOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CheckConsentOutput
{
  // #region Properties
  /**
   * Property hasConsent.
   *
   * Whether the user has granted consent for the requested scopes.
   * True if all requested scopes are already granted.
   *
   * @example true
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::CONSENT_READ])]
  #[SerializedName(serializedName: 'has_consent')]
  #[ApiProperty(
    description: 'Whether user has granted consent for all requested scopes',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: true,
    openapiContext: [
      'type' => 'boolean',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'boolean',
    ],
  )]
  public bool $hasConsent;

  /**
   * Property grantedScopes.
   *
   * List of scopes that have already been granted by the user.
   *
   * @example ["openid", "profile"]
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Groups(groups: [OAuthSerializationGroup::CONSENT_READ])]
  #[SerializedName(serializedName: 'granted_scopes')]
  #[ApiProperty(
    description: 'List of scopes already granted by the user',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: ['openid', 'profile'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
      'readOnly' => true,
      'example' => ['openid', 'profile'],
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
    ],
  )]
  public array $grantedScopes;

  /**
   * Property missingScopes.
   *
   * List of requested scopes that have not been granted yet.
   * Empty if all requested scopes are already granted.
   *
   * @example ["email"]
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Groups(groups: [OAuthSerializationGroup::CONSENT_READ])]
  #[SerializedName(serializedName: 'missing_scopes')]
  #[ApiProperty(
    description: 'List of requested scopes not yet granted',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: ['email'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
      'readOnly' => true,
      'example' => ['email'],
    ],
    jsonSchemaContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
    ],
  )]
  public array $missingScopes;

  /**
   * Property requiresConsentScreen.
   *
   * Whether the application should display a consent screen.
   * True if there are missing scopes that need user approval.
   *
   * @example true
   *
   * @since 1.0.0
   */
  #[Groups(groups: [OAuthSerializationGroup::CONSENT_READ])]
  #[SerializedName(serializedName: 'requires_consent_screen')]
  #[ApiProperty(
    description: 'Whether a consent screen should be displayed to the user',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: true,
    openapiContext: [
      'type' => 'boolean',
      'readOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'boolean',
    ],
  )]
  public bool $requiresConsentScreen;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Creates a new CheckConsentOutput instance.
   *
   * @since 1.0.0
   *
   * @param bool $hasConsent Whether consent is granted
   * @param list<string> $grantedScopes List of granted scopes
   * @param list<string> $missingScopes List of missing scopes
   * @param bool $requiresConsentScreen Whether consent screen is needed
   */
  public function __construct(
    bool $hasConsent,
    array $grantedScopes,
    array $missingScopes,
    bool $requiresConsentScreen,
  ) {
    $this->hasConsent = $hasConsent;
    $this->grantedScopes = $grantedScopes;
    $this->missingScopes = $missingScopes;
    $this->requiresConsentScreen = $requiresConsentScreen;
  }
  // #endregion
}
