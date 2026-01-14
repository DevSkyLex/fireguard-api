<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Query\Tenant\ListTenants;

use Shared\Application\Message\ResultMessage;
use Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantResult;

/**
 * Result ListTenantsResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTenantsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListTenantsResult class.
   *
   * @since 1.0.0
   *
   * @param list<GetTenantResult> $tenants the tenants
   * @param int $totalCount the total count
   */
  public function __construct(
    public array $tenants,
    public int $totalCount,
  ) {
  }
  // #endregion
}
