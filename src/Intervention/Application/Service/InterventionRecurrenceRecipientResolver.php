<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\ValueObject\OrganizationId;

use function array_intersect;
use function array_unique;
use function array_values;

/**
 * Service InterventionRecurrenceRecipientResolver.
 *
 * Resolves the fallback recipients of a recurrence materialization failure
 * notification when the recurrence carries no responsible member override:
 * the organization's active administrators, i.e. members whose effective
 * permissions grant `organization.interventions.plan` directly or through a
 * wildcard. Mirrors the administrator-detection rule
 * {@see \Maintenance\Application\Service\MaintenanceReminderRecipientResolver}
 * uses for `organization.maintenance.manage`, adapted to the planning
 * permission that governs intervention recurrences.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionRecurrenceRecipientResolver
{
  /**
   * Granted permission patterns that satisfy organization.interventions.plan.
   *
   * @var list<string>
   */
  private const array ADMIN_GRANTING_PERMISSIONS = [
    'organization.interventions.plan',
    'organization.interventions.*',
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
