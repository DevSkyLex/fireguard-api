<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Dto\Output\Tenant;

use Symfony\Component\Serializer\Attribute\Groups;
use Tenant\Presentation\Api\Serialization\TenantSerializationGroup;

/**
 * DTO TenantOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TenantOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * The tenant ID.
   */
  #[Groups([TenantSerializationGroup::READ])]
  public string $id = '';

  /**
   * Property name.
   *
   * The tenant name.
   */
  #[Groups([TenantSerializationGroup::READ])]
  public string $name = '';

  /**
   * Property isActive.
   *
   * Whether the tenant is active.
   */
  #[Groups([TenantSerializationGroup::READ])]
  public bool $isActive = true;

  /**
   * Property accessTokenTtl.
   *
   * Access token TTL in seconds.
   */
  #[Groups([TenantSerializationGroup::READ, TenantSerializationGroup::SETTINGS])]
  public int $accessTokenTtl = 3600;

  /**
   * Property refreshTokenTtl.
   *
   * Refresh token TTL in seconds.
   */
  #[Groups([TenantSerializationGroup::READ, TenantSerializationGroup::SETTINGS])]
  public int $refreshTokenTtl = 86400;

  /**
   * Property requirePkce.
   *
   * Whether PKCE is required.
   */
  #[Groups([TenantSerializationGroup::READ, TenantSerializationGroup::SETTINGS])]
  public bool $requirePkce = true;

  /**
   * Property createdAt.
   *
   * The creation timestamp.
   */
  #[Groups([TenantSerializationGroup::READ])]
  public string $createdAt = '';
  // #endregion
}
