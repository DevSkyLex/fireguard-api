<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Dto\Input\Tenant;

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
  public string $name = '';

  /**
   * Property accessTokenTtl.
   *
   * Access token TTL in seconds.
   */
  #[Assert\Positive(message: 'Access token TTL must be positive.')]
  #[Assert\Range(min: 300, max: 86400, notInRangeMessage: 'Access token TTL must be between 5 minutes and 24 hours.')]
  #[Groups([TenantSerializationGroup::WRITE])]
  public int $accessTokenTtl = 3600;

  /**
   * Property refreshTokenTtl.
   *
   * Refresh token TTL in seconds.
   */
  #[Assert\Positive(message: 'Refresh token TTL must be positive.')]
  #[Assert\Range(min: 3600, max: 2592000, notInRangeMessage: 'Refresh token TTL must be between 1 hour and 30 days.')]
  #[Groups([TenantSerializationGroup::WRITE])]
  public int $refreshTokenTtl = 86400;

  /**
   * Property requirePkce.
   *
   * Whether PKCE is required.
   */
  #[Groups([TenantSerializationGroup::WRITE])]
  public bool $requirePkce = true;

  /**
   * Property allowPublicClients.
   *
   * Whether public clients are allowed.
   */
  #[Groups([TenantSerializationGroup::WRITE])]
  public bool $allowPublicClients = false;
  // #endregion
}
