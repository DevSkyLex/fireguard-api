<?php

declare(strict_types=1);

namespace Maintenance\Application\Service;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\OrganizationId;

use function array_intersect;
use function array_unique;
use function array_values;

/**
 * Service MaintenanceReminderRecipientResolver.
 *
 * Resolves the recipients of maintenance reminders: the organization's
 * active administrators, i.e. members whose effective permissions grant
 * `organization.maintenance.manage` directly or through a wildcard. Mirrors
 * the administrator-detection rule
 * {@see \Organization\Application\Service\OrganizationLastAdminGuardService}
 * uses for `organization.members.manage` — no dedicated "notify all admins"
 * primitive exists elsewhere in the codebase, so this resolver adapts that
 * precedent to the maintenance permission.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceReminderRecipientResolver
{
  /**
   * Granted permission patterns that satisfy organization.maintenance.manage.
   *
   * @var list<string>
   */
  private const array ADMIN_GRANTING_PERMISSIONS = [
    'organization.maintenance.manage',
    'organization.maintenance.*',
    'organization.*',
    '*',
    '*.*',
    '*.*.*',
  ];

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRepositoryPort $members the organization member repository port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private OrganizationMemberRepositoryPort $members,
    private OrganizationAuthorizationPort $authorization,
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
    $organization = OrganizationId::fromString($organizationId);
    $adminUserIds = [];

    foreach ($this->members->findByOrganizationId($organization) as $member) {
      if (!$member->isActive()) {
        continue;
      }

      $permissions = $this->authorization->getUserPermissions($member->userId(), $organizationId);
      if ([] !== array_intersect($permissions, self::ADMIN_GRANTING_PERMISSIONS)) {
        $adminUserIds[] = $member->userId();
      }
    }

    return array_values(array_unique($adminUserIds));
  }
  // #endregion
}
