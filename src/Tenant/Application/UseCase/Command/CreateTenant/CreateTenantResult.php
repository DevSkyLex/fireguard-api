<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Command\CreateTenant;

/**
 * Result CreateTenantResult
 * @final
 *
 * Result of tenant creation.
 *
 * @category Result
 * @package Tenant\Application\UseCase\Command\CreateTenant
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateTenantResult
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $tenantId The created tenant ID.
   */
  public function __construct(
    public string $tenantId,
  ) {
  }
  //#endregion
}
