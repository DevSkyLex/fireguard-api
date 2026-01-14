<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Command\Tenant\CreateTenant;

use Shared\Application\Message\CommandMessage;
use Tenant\Domain\ValueObject\TenantSettings;

/**
 * Command CreateTenantCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateTenantCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $name the tenant name
   * @param TenantSettings|null $settings the tenant settings
   */
  public function __construct(
    public string $name,
    public ?TenantSettings $settings = null,
  ) {
  }
  // #endregion
}
