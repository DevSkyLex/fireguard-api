<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Organization\Application\Port\Inbound\OrganizationMemberNotificationDirectoryPort;

/** Resolves recipients through Organization's grouped effective-permission capability.
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionRecurrenceRecipientResolver
{
  public function __construct(private OrganizationMemberNotificationDirectoryPort $members)
  {
  }

  /**
   * @return list<string>
   */
  public function organizationAdministrators(string $organizationId): array
  {
    return $this->members->administratorUserIds($organizationId, 'organization.interventions.plan');
  }
}
