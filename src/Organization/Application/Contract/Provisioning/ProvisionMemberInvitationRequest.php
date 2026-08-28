<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Provisioning;

/**
 * Contract ProvisionMemberInvitationRequest.
 *
 * Cross-module request to provision one organization member invitation
 * programmatically (bulk CSV import). A real run is executed through
 * {@see \Organization\Application\UseCase\Command\Organization\InviteOrganizationMember\InviteOrganizationMemberHandler}
 * (the exact same use case the HTTP API uses), so the member-cap quota and
 * every conflict rule (pending invitation, active membership) apply
 * identically — there is no parallel invitation path. Roles are named, not
 * identified: the CSV carries organization role *names*, resolved to their
 * identifiers inside the Organization module before dispatching.
 *
 * `dryRun` validates without creating anything: the email is checked
 * structurally and every role name is resolved, but no invitation is
 * persisted and no email is sent. Deliberately lighter than the
 * Equipment/Facility dry runs — no quota projection and no
 * pending-invitation/active-member lookup — because an invitation is cheap
 * to re-attempt and the conflict answer would be stale by the time a real
 * run followed anyway (see `src/Import/MODULE.md`, "Dry-run mode").
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ProvisionMemberInvitationRequest
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $email the invited email address
   * @param string $invitedByUserId the inviting user identifier
   * @param list<string> $roleNames requested organization role names; empty means the organization's default `member` role
   * @param bool $dryRun when true, validates the email and role names without creating an invitation
   */
  public function __construct(
    public string $organizationId,
    public string $email,
    public string $invitedByUserId,
    public array $roleNames = [],
    public bool $dryRun = false,
  ) {
  }
  // #endregion
}
