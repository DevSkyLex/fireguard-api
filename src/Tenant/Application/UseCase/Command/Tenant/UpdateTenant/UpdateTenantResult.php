<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Command\Tenant\UpdateTenant;

use Shared\Application\Message\ResultMessage;

/**
 * Result UpdateTenantResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateTenantResult implements ResultMessage
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
