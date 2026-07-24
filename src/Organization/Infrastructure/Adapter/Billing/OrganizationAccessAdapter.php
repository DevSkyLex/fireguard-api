<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Billing;

use Billing\Application\Port\Outbound\OrganizationAccessPort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;

/**
 * Adapter OrganizationAccessAdapter.
 *
 * Implements the Billing module's authorization port by delegating to the
 * Organization module's authorization service, so Billing can enforce
 * organization permissions without depending on Organization internals.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationAccessAdapter implements OrganizationAccessPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationAccessAdapter class.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function hasPermission(string $userId, string $organizationId, string $permission): bool
  {
    return $this->authorization->hasPermission($userId, $organizationId, $permission);
  }
  // #endregion
}
