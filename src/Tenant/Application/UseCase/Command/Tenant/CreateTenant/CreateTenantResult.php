<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Command\Tenant\CreateTenant;

use Shared\Application\Message\ResultMessage;

/**
 * Result CreateTenantResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateTenantResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $tenantId the created tenant ID
   */
  public function __construct(
    public string $tenantId,
  ) {
  }
  // #endregion
}
