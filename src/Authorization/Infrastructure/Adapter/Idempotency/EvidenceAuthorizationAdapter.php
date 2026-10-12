<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Adapter\Idempotency;

use Authorization\Application\Port\Inbound\{AuthorizationPort, FreshAuthorizationPort};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyPlatformAuthorizationEvidencePort};

use function array_values;

/**
 * Class EvidenceAuthorizationAdapter
 *
 * Captures required successful platform predicates using current assignments while leaving grant-list projections uncaptured.
 *
 * @category Adapter
 */
final readonly class EvidenceAuthorizationAdapter implements AuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param AuthorizationPort $inner existing owner projections
   * @param FreshAuthorizationPort $fresh uncached current owner predicates
   * @param HttpIdempotencyPlatformAuthorizationEvidencePort $evidence original request requirement collector
   * @param HttpIdempotencyAccountPort $users current published caller tenant projection
   *
   * @return void
   */
  public function __construct(private AuthorizationPort $inner, private FreshAuthorizationPort $fresh, private HttpIdempotencyPlatformAuthorizationEvidencePort $evidence, private HttpIdempotencyAccountPort $users)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function hasPermission(string $userId, string $permission): bool
  {
    if (!$this->evidence->tracksPrincipal($userId)) {
      return $this->inner->hasPermission($userId, $permission);
    }
    $user = $this->users->current($userId);
    $granted = null !== $user && $user->active && $this->fresh->hasPermission($userId, $permission, $user->tenantId);
    if ($granted) {
      $this->evidence->recordPlatform($userId, 'permission', [$permission]);
    }

    return $granted;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRole(string $userId, string $roleName): bool
  {
    if (!$this->evidence->tracksPrincipal($userId)) {
      return $this->inner->hasRole($userId, $roleName);
    }

    return $this->hasAllRoles($userId, [$roleName]);
  }

  /**
   * {@inheritdoc}
   */
  public function hasAnyRole(string $userId, array $roleNames): bool
  {
    if (!$this->evidence->tracksPrincipal($userId)) {
      return $this->inner->hasAnyRole($userId, $roleNames);
    }
    $user = $this->users->current($userId);
    if (null === $user || !$user->active) {
      return false;
    }
    foreach ($roleNames as $name) {
      if ($this->fresh->hasRole($userId, $name, $user->tenantId)) {
        $this->evidence->recordPlatform($userId, 'roles_any', array_values($roleNames));

        return true;
      }
    }

    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAllRoles(string $userId, array $roleNames): bool
  {
    if (!$this->evidence->tracksPrincipal($userId)) {
      return $this->inner->hasAllRoles($userId, $roleNames);
    }
    $user = $this->users->current($userId);
    if (null === $user || !$user->active) {
      return false;
    }
    foreach ($roleNames as $name) {
      if (!$this->fresh->hasRole($userId, $name, $user->tenantId)) {
        return false;
      }
    }
    $this->evidence->recordPlatform($userId, 'roles_all', array_values($roleNames));

    return true;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserRoleNames(string $userId): array
  {
    return $this->inner->getUserRoleNames($userId);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserPermissionNames(string $userId): array
  {
    return $this->inner->getUserPermissionNames($userId);
  }
  // #endregion
}
