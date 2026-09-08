<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use Organization\Application\Contract\Provisioning\{ProvisionMemberInvitationRequest, ProvisionMemberInvitationResult};

/**
 * Port MemberInvitationProvisioningPort.
 *
 * Inbound port for programmatic member-invitation provisioning (the Import
 * module's bulk CSV member import), mirroring
 * `Equipment\Application\Port\Inbound\EquipmentProvisioningPort` and
 * `Facility\Application\Port\Inbound\FacilityProvisioningPort`: callers
 * depend on this port and its `Contract` types only — never on
 * `InviteOrganizationMemberCommand` or any Organization Domain type. Every
 * failure the invitation use case can raise is translated into a typed
 * outcome rather than rethrown, so a bulk caller's row loop never has to
 * catch this module's exceptions.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MemberInvitationProvisioningPort
{
  // #region Methods
  /**
   * Method provision.
   *
   * Provisions one member invitation (or, on a dry run, validates the email
   * and role names without creating anything).
   *
   * @since 1.0.0
   *
   * @param ProvisionMemberInvitationRequest $request the provisioning request
   *
   * @return ProvisionMemberInvitationResult the provisioning outcome
   */
  public function provision(ProvisionMemberInvitationRequest $request): ProvisionMemberInvitationResult;
  // #endregion
}
