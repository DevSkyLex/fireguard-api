<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Idempotency;

use Organization\Application\Port\Inbound\{FreshOrganizationAuthorizationPort, OrganizationPermissionCatalogPort};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOrganizationAuthorityPort;

/**
 * Class OrganizationHttpIdempotencyAuthorityAdapter
 *
 * Supplies uncached registered organization authority through the generic Shared receipt contract.
 *
 * @category Adapter
 */
final readonly class OrganizationHttpIdempotencyAuthorityAdapter implements HttpIdempotencyOrganizationAuthorityPort
{
  // #region Constructor
  /**
   * @param FreshOrganizationAuthorizationPort $authorization uncached current membership and grants
   * @param OrganizationPermissionCatalogPort $catalog registered required keys
   */
  public function __construct(private FreshOrganizationAuthorizationPort $authorization, private OrganizationPermissionCatalogPort $catalog)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function isMemberOf(string $principalId, string $organizationId): bool
  {
    return $this->authorization->isMemberOf($principalId, $organizationId);
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermission(string $principalId, string $organizationId, string $permission): bool
  {
    return $this->catalog->contains($permission) && $this->authorization->hasPermission($principalId, $organizationId, $permission);
  }
  // #endregion
}
