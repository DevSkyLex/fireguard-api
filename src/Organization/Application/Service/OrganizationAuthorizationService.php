<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Organization\Domain\ValueObject\OrganizationId;
use Symfony\Contracts\Service\ResetInterface;

use function count;
use function explode;

/**
 * Service OrganizationAuthorizationService.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationAuthorizationService implements OrganizationAuthorizationPort, ResetInterface
{
  /**
   * @var array<string, list<string>>
   */
  private array $permissionCache = [];

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
    $grantedPermissions = $this->getUserPermissions($userId, $organizationId);

    foreach ($grantedPermissions as $granted) {
      if ($this->permissionMatches($granted, $permission)) {
        return true;
      }
    }

    return false;
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
    $cacheKey = $userId . '|' . $organizationId;
    if (isset($this->permissionCache[$cacheKey])) {
      return $this->permissionCache[$cacheKey];
    }

    return $this->permissionCache[$cacheKey] = $this->memberRepository->getPermissionNamesForUserInOrganization(
      userId: $userId,
      organizationId: OrganizationId::fromString($organizationId),
    );
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

    foreach ($grantedSegments as $index => $grantedSegment) {
      $requiredSegment = $requiredSegments[$index] ?? null;
      $isLastGrantedSegment = $index === count($grantedSegments) - 1;

      if ('*' === $grantedSegment) {
        if ($isLastGrantedSegment) {
          return true;
        }

        if (null === $requiredSegment) {
          return false;
        }

        continue;
      }

      if (null === $requiredSegment || $grantedSegment !== $requiredSegment) {
        return false;
      }
    }

    return count($grantedSegments) === count($requiredSegments);
  }
  // #endregion
}
