<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Application\Contract\Member\OrganizationMemberGrant;
use Organization\Application\Port\Inbound\{OrganizationJoinAccessPort, OrganizationMemberGrantGuardPort, OrganizationPermissionGrantGuardPort};
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationJoinRepositoryPort};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationInvitationNotFoundException, OrganizationJoinException};
use Organization\Domain\ValueObject\{OrganizationInvitationId, OrganizationJoinMode};

use function array_diff;

/**
 * Service OrganizationMemberGrantGuardService.
 *
 * Applies the actor ceiling to effective roles and verifies the persisted
 * provenance of invitation and automatic-policy grants.
 *
 * @category Service
 */
final readonly class OrganizationMemberGrantGuardService implements OrganizationMemberGrantGuardPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param OrganizationPermissionGrantGuardPort $permissions the actor grant ceiling
   * @param OrganizationInvitationRepositoryPort $invitations the preapproved invitation source
   * @param OrganizationJoinRepositoryPort $joins the current automatic policy
   * @param OrganizationJoinAccessPort $access the automatic-role eligibility check
   */
  public function __construct(
    private OrganizationPermissionGrantGuardPort $permissions,
    private OrganizationInvitationRepositoryPort $invitations,
    private OrganizationJoinRepositoryPort $joins,
    private OrganizationJoinAccessPort $access,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method assertCanGrant.
   *
   * @param OrganizationMemberGrant $grant the server-owned authorization source
   * @param string $organizationId the organization scope
   * @param string $recipientEmail the recipient account email
   * @param list<string> $roleIds the effective role identifiers
   *
   * @return void
   */
  public function assertCanGrant(OrganizationMemberGrant $grant, string $organizationId, string $recipientEmail, array $roleIds): void
  {
    match ($grant->source) {
      OrganizationMemberGrant::ACTOR => $this->permissions->assertCanAssignRoles($grant->reference, $organizationId, $roleIds),
      OrganizationMemberGrant::OPERATOR => null,
      OrganizationMemberGrant::INVITATION => $this->assertInvitationGrant($grant->reference, $organizationId, $recipientEmail, $roleIds),
      OrganizationMemberGrant::AUTOMATIC_JOIN => $this->assertAutomaticJoinGrant($organizationId, $grant->reference, $roleIds),
      default => throw new OrganizationAccessDeniedException('The membership grant is no longer authorized.'),
    };
  }

  /**
   * Method assertInvitationGrant
   *
   * Preserves the current invitation's exact organization, recipient and approved role set.
   *
   * @access private
   *
   * @param string $invitationId the persisted invitation reference
   * @param string $organizationId the organization scope
   * @param string $recipientEmail the recipient account email
   * @param list<string> $roleIds the effective role identifiers
   *
   * @return void
   */
  private function assertInvitationGrant(string $invitationId, string $organizationId, string $recipientEmail, array $roleIds): void
  {
    $invitation = $this->invitations->findById(OrganizationInvitationId::fromString($invitationId));
    if (null !== $invitation && (string) $invitation->organizationId() === $organizationId
      && $invitation->status()->isPending() && !$invitation->isExpired()
      && (string) $invitation->email() === $recipientEmail) {
      $approved = $this->invitations->findRoleIdsForInvitation($invitation->id());
      if ([] === array_diff($roleIds, $approved) && [] === array_diff($approved, $roleIds)) {
        return;
      }
    }

    throw OrganizationInvitationNotFoundException::withToken();
  }

  /**
   * Method assertAutomaticJoinGrant
   *
   * Rechecks the configured automatic role and its current eligibility before granting membership.
   *
   * @access private
   *
   * @param string $organizationId the organization scope
   * @param string $roleId the server-owned policy role reference
   * @param list<string> $roleIds the effective role identifiers
   *
   * @return void
   */
  private function assertAutomaticJoinGrant(string $organizationId, string $roleId, array $roleIds): void
  {
    $policy = $this->joins->policy($organizationId);
    if ($policy->organizationId === $organizationId && OrganizationJoinMode::AUTOMATIC === $policy->mode
      && $policy->roleId === $roleId && [$roleId] === $roleIds) {
      $this->access->assertEligibleRole($organizationId, $roleId);

      return;
    }

    throw new OrganizationJoinException('organization_join_unavailable');
  }
  // #endregion
}
