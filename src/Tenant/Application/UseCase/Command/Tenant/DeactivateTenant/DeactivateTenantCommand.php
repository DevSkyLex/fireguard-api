<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Command\Tenant\DeactivateTenant;

use Shared\Application\Message\CommandMessage;

/**
 * Command DeactivateTenantCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeactivateTenantCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $tenantId the tenant ID
   */
  public function __construct(
    public string $tenantId,
  ) {
  }
}
