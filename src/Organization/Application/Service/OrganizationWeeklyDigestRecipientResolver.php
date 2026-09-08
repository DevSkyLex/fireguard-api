<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\OrganizationId;

use function array_intersect;
use function array_unique;
use function array_values;

/**
 * Service OrganizationWeeklyDigestRecipientResolver.
 *
 * Resolves the recipients of the weekly organization digest: the
 * organization's active administrators, i.e. members whose effective
 * permissions grant `organization.settings.write` directly or through a
 * wildcard — the permission that governs administering the organization
 * (including turning this digest off). Mirrors the administrator-detection
 * rule of {@see \Inspection\Application\Service\NonConformitySlaRecipientResolver}
 * and `Maintenance\Application\Service\MaintenanceReminderRecipientResolver`,
 * adapted to the organization-administration permission.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationWeeklyDigestRecipientResolver
{
  /**
   * Granted permission patterns that satisfy organization.settings.write.
   *
   * @var list<string>
   */
  private const array ADMIN_GRANTING_PERMISSIONS = [
    'organization.settings.write',
    'organization.settings.*',
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
