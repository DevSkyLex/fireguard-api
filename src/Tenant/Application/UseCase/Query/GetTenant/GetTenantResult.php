<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Query\GetTenant;

use DateTimeImmutable;
use Tenant\Domain\ValueObject\TenantSettings;

/**
 * Result GetTenantResult
 * @final
 *
 * Result of getting a tenant.
 *
 * @category Result
 * @package Tenant\Application\UseCase\Query\GetTenant
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetTenantResult
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $tenantId The tenant ID.
   * @param string $name The tenant name.
   * @param TenantSettings $settings The tenant settings.
   * @param bool $isActive Whether the tenant is active.
   * @param DateTimeImmutable $createdAt The creation timestamp.
   */
  public function __construct(
    public string $tenantId,
    public string $name,
    public TenantSettings $settings,
    public bool $isActive,
    public DateTimeImmutable $createdAt,
  ) {
  }
  //#endregion
}
