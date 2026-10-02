<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use Organization\Application\Contract\Member\OrganizationMemberGrant;

/**
 * Port OrganizationMemberGrantGuardPort.
 *
 * Checks the resolved role set and the provenance of preapproved grants
 * before the membership use case persists any change.
 *
 * @category Port
 */
interface OrganizationMemberGrantGuardPort
{
  // #region Methods
  /**
   * Method assertCanGrant.
   *
   * @param OrganizationMemberGrant $grant the server-owned authorization source
   * @param string $organizationId the organization scope
   * @param string $recipientEmail the current recipient account email
   * @param list<string> $roleIds the resolved role identifiers
   *
   * @return void
   *
   * @throws \Organization\Domain\Exception\OrganizationAccessDeniedException when the grant has no valid authorization source
   * @throws \Organization\Domain\Exception\OrganizationInvitationNotFoundException when invitation provenance no longer matches
   * @throws \Organization\Domain\Exception\OrganizationJoinException when the automatic policy no longer authorizes the role
   */
  public function assertCanGrant(OrganizationMemberGrant $grant, string $organizationId, string $recipientEmail, array $roleIds): void;
  // #endregion
}
