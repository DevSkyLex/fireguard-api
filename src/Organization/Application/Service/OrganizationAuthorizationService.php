<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\OrganizationId;

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
final readonly class OrganizationAuthorizationService implements OrganizationAuthorizationPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationAuthorizationService class.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository port
   */
  public function __construct(
    private OrganizationMemberRepositoryPort $memberRepository,
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
    return $this->memberRepository->getPermissionNamesForUserInOrganization(
      userId: $userId,
      organizationId: OrganizationId::fromString($organizationId),
    );
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
