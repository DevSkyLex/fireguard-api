<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Adapter\Idempotency;

use Authorization\Application\Port\Inbound\FreshAuthorizationPort;
use Authorization\Application\Port\Outbound\PermissionCatalogPort;
use Shared\Application\Contract\Http\HttpIdempotencyAccount;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyPlatformAuthorityPort;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

use function array_map;
use function in_array;
use function str_starts_with;
use function strtoupper;

/**
 * Class AuthorizationHttpIdempotencyPlatformAuthorityAdapter
 *
 * Rechecks fresh registered assignments and configured native role hierarchy before saved response disclosure.
 *
 * @category Adapter
 */
final readonly class AuthorizationHttpIdempotencyPlatformAuthorityAdapter implements HttpIdempotencyPlatformAuthorityPort
{
  // #region Constructor
  /**
   * @param FreshAuthorizationPort $authorization uncached current scoped assignments
   * @param PermissionCatalogPort $catalog registered technical vocabulary
   * @param RoleHierarchyInterface $hierarchy configured native hierarchy
   */
  public function __construct(private FreshAuthorizationPort $authorization, private PermissionCatalogPort $catalog, private RoleHierarchyInterface $hierarchy)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function permitsKey(HttpIdempotencyAccount $account, string $key): bool
  {
    if ('' === $key) {
      return true;
    }
    if (!str_starts_with($key, 'ROLE_')) {
      return $this->hasPermission($account, $key);
    }
    $roles = array_map(static fn (string $name): string => 'ROLE_' . strtoupper($name), $this->authorization->roleNames($account->principalId, $account->tenantId));
    $roles[] = 'ROLE_USER';
    if ($account->emailVerified) {
      $roles[] = 'ROLE_VERIFIED';
    }

    return $account->active && in_array($key, $this->hierarchy->getReachableRoleNames($roles), true);
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermission(HttpIdempotencyAccount $account, string $permission): bool
  {
    return $this->catalog->contains($permission) && $this->authorization->hasPermission($account->principalId, $permission, $account->tenantId);
  }

  /**
   * {@inheritdoc}
   */
  public function hasRole(HttpIdempotencyAccount $account, string $roleName): bool
  {
    return $this->authorization->hasRole($account->principalId, $roleName, $account->tenantId);
  }
  // #endregion
}
