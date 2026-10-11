<?php

declare(strict_types=1);

namespace Maintenance\Application\Service;

use Organization\Application\Port\Inbound\OrganizationMemberNotificationDirectoryPort;

/**
 * Service MaintenanceReminderRecipientResolver.
 *
 * Resolves the recipients of maintenance reminders: the organization's
 * active administrators, i.e. members whose effective permissions grant
 * `organization.maintenance.manage` directly or through a wildcard. Mirrors
 * the owner-published administrator directory with its current effective-permission policy.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceReminderRecipientResolver
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberNotificationDirectoryPort $members the organization member repository port
   */
  public function __construct(
    private OrganizationMemberNotificationDirectoryPort $members,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method organizationAdministrators.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return list<string> the active administrators' user identifiers
   */
  public function organizationAdministrators(string $organizationId): array
  {
    return $this->members->administratorUserIds($organizationId, 'organization.maintenance.manage');
  }
  // #endregion
}
