<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Query\GetTenant;

use DateTimeImmutable;
use Tenant\Domain\ValueObject\TenantSettings;

/**
 * Result GetTenantResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetTenantResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string            $tenantId  the tenant ID
   * @param string            $name      the tenant name
   * @param TenantSettings    $settings  the tenant settings
   * @param bool              $isActive  whether the tenant is active
   * @param DateTimeImmutable $createdAt the creation timestamp
   */
  public function __construct(
    public string $tenantId,
    public string $name,
    public TenantSettings $settings,
    public bool $isActive,
    public DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion
}
