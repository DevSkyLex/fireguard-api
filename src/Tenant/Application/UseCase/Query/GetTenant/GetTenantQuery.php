<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Query\GetTenant;

/**
 * Query GetTenantQuery
 * @final
 *
 * Query to get a tenant by ID.
 *
 * @category Query
 * @package Tenant\Application\UseCase\Query\GetTenant
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetTenantQuery
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $tenantId The tenant ID.
   */
  public function __construct(
    public string $tenantId,
  ) {
  }
  //#endregion
}
