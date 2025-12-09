<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * DTO TenantOutput
 * @final
 *
 * Output DTO for tenant data.
 *
 * @category DTO
 * @package Tenant\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TenantOutput
{
  //#region Properties
  /**
   * Property id
   *
   * The tenant ID.
   *
   * @var string
   */
  #[Groups(['tenant:read'])]
  public string $id = '';

  /**
   * Property name
   *
   * The tenant name.
   *
   * @var string
   */
  #[Groups(['tenant:read'])]
  public string $name = '';

  /**
   * Property isActive
   *
   * Whether the tenant is active.
   *
   * @var bool
   */
  #[Groups(['tenant:read'])]
  public bool $isActive = true;

  /**
   * Property accessTokenTtl
   *
   * Access token TTL in seconds.
   *
   * @var int
   */
  #[Groups(['tenant:read', 'tenant:settings'])]
  public int $accessTokenTtl = 3600;

  /**
   * Property refreshTokenTtl
   *
   * Refresh token TTL in seconds.
   *
   * @var int
   */
  #[Groups(['tenant:read', 'tenant:settings'])]
  public int $refreshTokenTtl = 86400;

  /**
   * Property requirePkce
   *
   * Whether PKCE is required.
   *
   * @var bool
   */
  #[Groups(['tenant:read', 'tenant:settings'])]
  public bool $requirePkce = true;

  /**
   * Property createdAt
   *
   * The creation timestamp.
   *
   * @var string
   */
  #[Groups(['tenant:read'])]
  public string $createdAt = '';
  //#endregion
}
