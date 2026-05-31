<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Dto\Input\Tenant;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Tenant\Presentation\Api\Serialization\TenantSerializationGroup;

/**
 * DTO TenantInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TenantInput
{
  // #region Properties
  /**
   * Property name.
   *
   * The tenant name.
   */
  #[Assert\NotBlank(message: 'Tenant name is required.')]
  #[Assert\Length(min: 3, max: 100, minMessage: 'Tenant name must be at least 3 characters.')]
  #[Groups([TenantSerializationGroup::WRITE])]
  #[ApiProperty(
    description: 'Tenant name.',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 'Acme Corp',
    openapiContext: [
      'type' => 'string',
      'minLength' => 3,
      'maxLength' => 100,
    ],
  )]
  public string $name = '';

  /**
   * Property accessTokenTtl.
   *
   * Access token TTL in seconds.
   */
  #[Assert\Positive(message: 'Access token TTL must be positive.')]
  #[Assert\Range(min: 300, max: 86400, notInRangeMessage: 'Access token TTL must be between 5 minutes and 24 hours.')]
  #[Groups([TenantSerializationGroup::WRITE])]
  #[ApiProperty(
    description: 'Access token TTL in seconds.',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 3600,
    openapiContext: [
      'type' => 'integer',
      'minimum' => 300,
      'maximum' => 86400,
    ],
  )]
  public int $accessTokenTtl = 3600;

  /**
   * Property refreshTokenTtl.
   *
   * Refresh token TTL in seconds.
   */
  #[Assert\Positive(message: 'Refresh token TTL must be positive.')]
  #[Assert\Range(min: 3600, max: 2592000, notInRangeMessage: 'Refresh token TTL must be between 1 hour and 30 days.')]
  #[Groups([TenantSerializationGroup::WRITE])]
  #[ApiProperty(
    description: 'Refresh token TTL in seconds.',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 86400,
    openapiContext: [
      'type' => 'integer',
      'minimum' => 3600,
      'maximum' => 2592000,
    ],
  )]
  public int $refreshTokenTtl = 86400;

  /**
   * Property requirePkce.
   *
   * Whether PKCE is required.
   */
  #[Groups([TenantSerializationGroup::WRITE])]
  #[ApiProperty(
    description: 'Whether PKCE is required for OAuth2 flows.',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: true,
    openapiContext: [
      'type' => 'boolean',
    ],
  )]
  public bool $requirePkce = true;

  /**
   * Property allowPublicClients.
   *
   * Whether public clients are allowed.
   */
  #[Groups([TenantSerializationGroup::WRITE])]
  #[ApiProperty(
    description: 'Whether public clients are allowed.',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: false,
    openapiContext: [
      'type' => 'boolean',
    ],
  )]
  public bool $allowPublicClients = false;

  /**
   * Property allowedScopes.
   *
   * Allowed OAuth2 scopes for this tenant.
   *
   * @var list<string>
   */
  #[Assert\All([
    new Assert\Type('string'),
    new Assert\NotBlank(message: 'Scope entries must be non-empty.'),
  ])]
  #[Groups([TenantSerializationGroup::WRITE])]
  #[ApiProperty(
    description: 'Allowed OAuth2 scopes for this tenant.',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    example: ['openid', 'profile', 'email'],
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
    ],
  )]
  public array $allowedScopes = ['openid', 'profile', 'email'];

  /**
   * Property customIssuer.
   *
   * Custom issuer URL for this tenant.
   */
  #[Assert\Url(message: 'Custom issuer must be a valid URL.', requireTld: false)]
  #[Groups([TenantSerializationGroup::WRITE])]
  #[ApiProperty(
    description: 'Custom issuer URL for this tenant.',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    example: 'https://auth.example.com',
    openapiContext: [
      'type' => 'string',
      'format' => 'uri',
    ],
  )]
  public ?string $customIssuer = null;
  // #endregion
}
