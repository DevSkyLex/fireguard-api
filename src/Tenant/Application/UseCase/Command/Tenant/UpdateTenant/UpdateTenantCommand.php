<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Command\Tenant\UpdateTenant;

use Shared\Application\Message\CommandMessage;
use Tenant\Domain\ValueObject\TenantSettings;

/**
 * Command UpdateTenantCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateTenantCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $tenantId the tenant ID
   * @param string|null $name the new tenant name
   * @param TenantSettings|null $settings the new tenant settings
   */
  public function __construct(
    public string $tenantId,
    public ?string $name = null,
    public ?TenantSettings $settings = null,
  ) {
  }
}
