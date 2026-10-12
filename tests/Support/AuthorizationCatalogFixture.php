<?php

declare(strict_types=1);

namespace Tests\Support;

use Audit\Infrastructure\Adapter\Authorization\AuditPermissionContributionAdapter;
use Authorization\Infrastructure\Adapter\Authorization\{AuthorizationPermissionContributionAdapter, SystemRoleContributionAdapter};
use Authorization\Infrastructure\Catalog\{PermissionCatalog, RoleCatalog};
use OAuth\Infrastructure\Adapter\Authorization\OAuthPermissionContributionAdapter;
use Otp\Infrastructure\Adapter\Authorization\OtpPermissionContributionAdapter;
use Session\Infrastructure\Adapter\Authorization\SessionPermissionContributionAdapter;
use Tenant\Infrastructure\Adapter\Authorization\TenantPermissionContributionAdapter;
use TrustedDevice\Infrastructure\Adapter\Authorization\TrustedDevicePermissionContributionAdapter;
use User\Infrastructure\Adapter\Authorization\UserPermissionContributionAdapter;

/**
 * Class AuthorizationCatalogFixture
 *
 * Composes installed contributions explicitly for container-free unit assertions.
 *
 * @category TestSupport
 */
final class AuthorizationCatalogFixture
{
  // #region Methods
  /**
   * Method permissions
   *
   * @access public
   *
   * @return PermissionCatalog installed owner vocabulary
   */
  public static function permissions(): PermissionCatalog
  {
    return new PermissionCatalog([
      UserPermissionContributionAdapter::class,
      OAuthPermissionContributionAdapter::class,
      AuthorizationPermissionContributionAdapter::class,
      SessionPermissionContributionAdapter::class,
      OtpPermissionContributionAdapter::class,
      TrustedDevicePermissionContributionAdapter::class,
      TenantPermissionContributionAdapter::class,
      AuditPermissionContributionAdapter::class,
    ]);
  }

  /**
   * Method roles
   *
   * @access public
   *
   * @return RoleCatalog installed bootstrap policy
   */
  public static function roles(): RoleCatalog
  {
    return new RoleCatalog([SystemRoleContributionAdapter::class]);
  }
  // #endregion
}
