<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationStatus};
use Symfony\Contracts\Service\ResetInterface;

use function array_key_exists;
use function count;
use function explode;

/**
 * Service OrganizationAuthorizationService.
 *
 * @category Service
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationAuthorizationService implements OrganizationAuthorizationPort, ResetInterface
{
  private int $observedRevision = -1;

  /**
   * @var array<string, list<string>>
   */
  private array $permissionCache = [];

  /**
   * Per-request memo of active-membership answers, keyed the same way as
   * {@see self::$permissionCache}. Deliberately request-local and not written
   * to the shared cache: it is only read on the denial path, where one extra
   * count per request is cheaper than a second invalidation surface to keep
   * in step with membership changes.
   *
   * @var array<string, bool>
   */
  private array $membershipCache = [];

  /**
   * Per-request memo of organization statuses, keyed by organization id.
   * Request-local for the same reason as {@see self::$membershipCache}.
   *
   * @var array<string, OrganizationStatus|null>
   */
  private array $statusCache = [];

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * OrganizationAuthorizationService class.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   */
  public function __construct(
    private readonly OrganizationMemberRepositoryPort $memberRepository,
    private readonly OrganizationRepositoryPort $organizationRepository,
    private readonly ?OrganizationCacheInvalidator $cacheInvalidator = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method hasPermission.
   *
   * Checks whether a user has a required organization permission.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   * @param string $permission the permission to check
   *
   * @return bool true when granted, false otherwise
   */
  public function hasPermission(string $userId, string $organizationId, string $permission): bool
  {
    if (!$this->isPermissionExercisable($organizationId, $permission)) {
      return false;
    }

    $grantedPermissions = $this->getUserPermissions($userId, $organizationId);

    foreach ($grantedPermissions as $granted) {
      if ($this->permissionMatches($granted, $permission)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Method resolveAccess.
   *
   * Resolves a permission check into a three-state decision.
   *
   * The membership lookup runs only when the permission is not granted: a
   * granted permission already proves an active membership, since
   * {@see OrganizationMemberRepositoryPort::getPermissionNamesForUserInOrganization()}
   * resolves permissions through the same `isActive` membership row. The
   * authorized path therefore costs exactly what {@see self::hasPermission()}
   * costs, and only a denial pays for the extra count.
   *
   * @since 1.1.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   * @param string $permission the permission to check
   *
   * @return OrganizationAccessDecision the resolved decision
   */
  public function resolveAccess(string $userId, string $organizationId, string $permission): OrganizationAccessDecision
  {
    if ($this->hasPermission($userId, $organizationId, $permission)) {
      return OrganizationAccessDecision::GRANTED;
    }

    return $this->isActiveMember($userId, $organizationId)
      ? OrganizationAccessDecision::MISSING_PERMISSION
      : OrganizationAccessDecision::OUTSIDE_SCOPE;
  }

  /**
   * Method isMemberOf.
   *
   * Tells whether the user holds an active membership in the organization.
   *
   * @since 1.1.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   *
   * @return bool true when an active membership exists
   */
  public function isMemberOf(string $userId, string $organizationId): bool
  {
    return $this->isActiveMember($userId, $organizationId);
  }

  /**
   * Method getUserPermissions.
   *
   * Returns all effective organization permissions for a user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   *
   * @return list<string> the resolved permission names
   */
  public function getUserPermissions(string $userId, string $organizationId): array
  {
    $this->synchronizeInvalidation();
    $cacheKey = $userId . '|' . $organizationId;
    if (isset($this->permissionCache[$cacheKey]) && [] !== $this->permissionCache[$cacheKey]) {
      return $this->permissionCache[$cacheKey];
    }

    $permissions = $this->memberRepository->getPermissionNamesForUserInOrganization(
      userId: $userId,
      organizationId: OrganizationId::fromString($organizationId),
    );

    return $this->permissionCache[$cacheKey] = $permissions;
  }

  /**
   * Method assertGrantedPermissions.
   *
   * Resolves the user's effective permissions once and asserts
   * that each required permission is granted.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   * @param list<string> $permissions the permission names to assert
   *
   * @throws OrganizationAccessDeniedException when one of the required permissions is missing
   *
   * @return void Returns nothing. Throws when access must be denied.
   */
  public function assertGrantedPermissions(string $userId, string $organizationId, array $permissions): void
  {
    $grantedPermissions = $this->getUserPermissions($userId, $organizationId);
    foreach ($permissions as $permission) {
      if (!$this->isPermissionExercisable($organizationId, $permission)) {
        throw OrganizationAccessDeniedException::organizationSuspended(
          $permission,
          OrganizationStatus::ARCHIVED === $this->organizationStatus($organizationId),
        );
      }

      $matched = false;
      foreach ($grantedPermissions as $granted) {
        if ($this->permissionMatches($granted, $permission)) {
          $matched = true;

          break;
        }
      }
      if (!$matched) {
        throw OrganizationAccessDeniedException::missingPermission($permission);
      }
    }
  }

  public function reset(): void
  {
    $this->permissionCache = [];
    $this->membershipCache = [];
    $this->statusCache = [];
  }

  /**
   * Method isActiveMember.
   *
   * Resolves, and memoizes for the request, whether the user holds an active
   * membership in the organization.
   *
   * @since 1.1.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   *
   * @return bool true when an active membership exists
   */
  private function isActiveMember(string $userId, string $organizationId): bool
  {
    $this->synchronizeInvalidation();
    $cacheKey = $userId . '|' . $organizationId;
    if (isset($this->membershipCache[$cacheKey])) {
      return $this->membershipCache[$cacheKey];
    }

    return $this->membershipCache[$cacheKey] = $this->memberRepository->hasActiveMembership(
      organizationId: OrganizationId::fromString($organizationId),
      userId: $userId,
    );
  }

  /**
   * Method isPermissionExercisable.
   *
   * Tells whether a permission may be exercised at all right now, before any
   * grant is consulted. A suspended organization is read-only: every write is
   * refused regardless of what the caller holds, except the one that restores
   * it.
   *
   * The check reads the *requested* permission, never the granted set: a
   * member may hold a wildcard such as `organization.*`, and filtering the
   * grants would strip their reads along with their writes.
   *
   * @since 1.2.0
   *
   * @param string $organizationId the organization identifier
   * @param string $permission the permission being requested
   *
   * @return bool false when the organization's state forbids this permission
   */
  private function isPermissionExercisable(string $organizationId, string $permission): bool
  {
    // Ordered so a plain read never pays for the status lookup.
    if (OrganizationPermissionCatalog::isRead($permission)) {
      return true;
    }

    // Both non-active statuses are read-only, and both let a narrow set of
    // permissions through — but not the same set, and for different reasons.
    return match ($this->organizationStatus($organizationId)) {
      // The organization must stay restorable from inside.
      OrganizationStatus::SUSPENDED => OrganizationPermissionCatalog::isSuspensionEscapeHatch($permission),
      // Archiving is terminal, so there is no escape hatch to preserve — the
      // platform-only rule for reopening lives in RestoreOrganizationProcessor.
      // What passes here passes because a handler downstream already answers
      // the archived state more precisely than a 403 would.
      OrganizationStatus::ARCHIVED => OrganizationPermissionCatalog::isArchivalGuardedDownstream($permission),
      default => true,
    };
  }

  /**
   * Method organizationStatus.
   *
   * Resolves, and memoizes for the request, an organization's status.
   *
   * Request-local only, like {@see self::$membershipCache}: writing it to the
   * shared cache would add a second invalidation surface to keep in step with
   * suspend and restore, and the answer is already amortized across every
   * permission check in the request.
   *
   * @since 1.2.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return OrganizationStatus|null the status, or null when unknown
   */
  private function organizationStatus(string $organizationId): ?OrganizationStatus
  {
    $this->synchronizeInvalidation();
    if (array_key_exists($organizationId, $this->statusCache)) {
      return $this->statusCache[$organizationId];
    }

    $status = $this->organizationRepository->statusOf(OrganizationId::fromString($organizationId));

    return $this->statusCache[$organizationId] = $status;
  }

  /**
   * Method permissionMatches.
   *
   * Checks whether a granted permission pattern matches a required permission.
   *
   * @since 1.0.0
   *
   * @param string $granted the granted permission pattern
   * @param string $required the required permission
   *
   * @return bool true when pattern matches, false otherwise
   */
  private function permissionMatches(string $granted, string $required): bool
  {
    if ('' === $granted || '' === $required) {
      return false;
    }

    if ('*' === $granted || '*.*' === $granted || '*.*.*' === $granted || $granted === $required) {
      return true;
    }

    $grantedSegments = explode('.', $granted);
    $requiredSegments = explode('.', $required);
    $matches = count($grantedSegments) === count($requiredSegments);

    foreach ($grantedSegments as $index => $grantedSegment) {
      $requiredSegment = $requiredSegments[$index] ?? null;
      $isLastGrantedSegment = $index === count($grantedSegments) - 1;

      if ('*' === $grantedSegment && $isLastGrantedSegment) {
        $matches = true;

        break;
      }
      if (null === $requiredSegment || ('*' !== $grantedSegment && $grantedSegment !== $requiredSegment)) {
        $matches = false;

        break;
      }
    }

    return $matches;
  }

  /**
   * Clears request-local answers after a mutation in this unit of work.
   *
   * @since 1.1.0
   */
  private function synchronizeInvalidation(): void
  {
    $revision = $this->cacheInvalidator?->revision() ?? 0;
    if ($this->observedRevision !== $revision) {
      $this->reset();
      $this->observedRevision = $revision;
    }
  }
  // #endregion
}
