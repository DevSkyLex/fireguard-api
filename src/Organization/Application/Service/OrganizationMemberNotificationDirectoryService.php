<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Application\Port\Inbound\OrganizationMemberNotificationDirectoryPort;
use Organization\Application\Port\Outbound\OrganizationMemberPermissionReadPort;

use function count;
use function explode;

/**
 * Service OrganizationMemberNotificationDirectoryService.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationMemberNotificationDirectoryService implements OrganizationMemberNotificationDirectoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberPermissionReadPort $permissions the organization-scoped batch read
   */
  public function __construct(private OrganizationMemberPermissionReadPort $permissions)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function administratorUserIds(string $organizationId, string $permission): array
  {
    $recipients = [];
    foreach ($this->permissions->activeUserPermissions($organizationId) as $userId => $grants) {
      foreach ($grants as $grant) {
        if ($this->matches($grant, $permission)) {
          $recipients[] = $userId;

          break;
        }
      }
    }

    return $recipients;
  }

  /**
   * Matches the same segmented wildcards as the authorization capability.
   *
   * @since 1.0.0
   *
   * @param string $granted the effective permission pattern
   * @param string $required the required notification administrator permission
   *
   * @return bool whether the grant matches
   */
  private function matches(string $granted, string $required): bool
  {
    if ('' === $granted || '' === $required) {
      return false;
    }
    if ('*' === $granted || '*.*' === $granted || '*.*.*' === $granted || $granted === $required) {
      return true;
    }
    $grantedSegments = explode('.', $granted);
    $requiredSegments = explode('.', $required);
    foreach ($grantedSegments as $index => $segment) {
      if ('*' === $segment && $index === count($grantedSegments) - 1) {
        return true;
      }
      if (!isset($requiredSegments[$index]) || ('*' !== $segment && $segment !== $requiredSegments[$index])) {
        return false;
      }
    }

    return count($grantedSegments) === count($requiredSegments);
  }
  // #endregion
}
