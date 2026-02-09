<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Command\Tenant\DeleteTenant;

use Shared\Application\Message\CommandMessage;

/**
 * Command DeleteTenantCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteTenantCommand implements CommandMessage
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
