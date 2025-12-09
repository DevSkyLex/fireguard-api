<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Command\CreateTenant;

use Tenant\Domain\ValueObject\TenantSettings;

/**
 * Command CreateTenantCommand
 * @final
 *
 * Command to create a new tenant.
 *
 * @category Command
 * @package Tenant\Application\UseCase\Command\CreateTenant
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateTenantCommand
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $name The tenant name.
   * @param TenantSettings|null $settings The tenant settings.
   */
  public function __construct(
    public string $name,
    public ?TenantSettings $settings = null,
  ) {
  }
  //#endregion
}
