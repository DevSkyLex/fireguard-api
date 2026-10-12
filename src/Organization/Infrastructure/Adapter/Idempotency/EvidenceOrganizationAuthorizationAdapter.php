<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Idempotency;

use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationPermissionCatalogPort};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyAuthorizationEvidencePort;

/**
 * Class EvidenceOrganizationAuthorizationAdapter
 *
 * Records only successful required gates for the authenticated original operation, never the caller's full grant set.
 *
 * @category Adapter
 */
final readonly class EvidenceOrganizationAuthorizationAdapter implements OrganizationAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param OrganizationAuthorizationPort $inner current owner authorization capability
   * @param HttpIdempotencyAuthorizationEvidencePort $evidence request-local successful requirement collector
   * @param OrganizationPermissionCatalogPort $catalog registered permission vocabulary
   *
   * @return void
   */
  public function __construct(
    private OrganizationAuthorizationPort $inner,
    private HttpIdempotencyAuthorizationEvidencePort $evidence,
    private OrganizationPermissionCatalogPort $catalog,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function hasPermission(string $userId, string $organizationId, string $permission): bool
  {
    $permission = $this->catalog->canonicalName($permission);
    if (null === $permission) {
      return false;
    }
    if (!$this->catalog->contains($permission) || !$this->inner->hasPermission($userId, $organizationId, $permission)) {
      return false;
    }
    $this->evidence->record($userId, $organizationId, [$permission]);

    return true;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveAccess(string $userId, string $organizationId, string $permission): OrganizationAccessDecision
  {
    $canonical = $this->catalog->canonicalName($permission);
    if (null !== $canonical) {
      $permission = $canonical;
    }
    if (!$this->catalog->contains($permission)) {
      return $this->inner->isMemberOf($userId, $organizationId)
        ? OrganizationAccessDecision::MISSING_PERMISSION
        : OrganizationAccessDecision::OUTSIDE_SCOPE;
    }
    $decision = $this->inner->resolveAccess($userId, $organizationId, $permission);
    if ($decision->isGranted()) {
      $this->evidence->record($userId, $organizationId, [$permission]);
    }

    return $decision;
  }

  /**
   * {@inheritdoc}
   */
  public function isMemberOf(string $userId, string $organizationId): bool
  {
    $member = $this->inner->isMemberOf($userId, $organizationId);
    if ($member) {
      $this->evidence->record($userId, $organizationId, [], true);
    }

    return $member;
  }

  /**
   * {@inheritdoc}
   */
  public function assertGrantedPermissions(string $userId, string $organizationId, array $permissions): void
  {
    $canonical = [];
    foreach ($permissions as $permission) {
      $resolved = $this->catalog->canonicalName($permission);
      if (null === $resolved) {
        $this->catalog->assertKnown([$permission]);
      }
      $canonical[] = $resolved ?? $permission;
    }
    $permissions = $canonical;
    $this->inner->assertGrantedPermissions($userId, $organizationId, $permissions);
    $this->evidence->record($userId, $organizationId, $permissions);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserPermissions(string $userId, string $organizationId): array
  {
    return $this->inner->getUserPermissions($userId, $organizationId);
  }
  // #endregion
}
