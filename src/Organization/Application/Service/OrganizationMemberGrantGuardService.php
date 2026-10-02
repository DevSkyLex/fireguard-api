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
    if (OrganizationMemberGrant::ACTOR === $grant->source) {
      $this->permissions->assertCanAssignRoles($grant->reference, $organizationId, $roleIds);

      return;
    }
    if (OrganizationMemberGrant::OPERATOR === $grant->source) {
      return;
    }
    if (OrganizationMemberGrant::INVITATION === $grant->source) {
      $invitation = $this->invitations->findById(OrganizationInvitationId::fromString($grant->reference));
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
    if (OrganizationMemberGrant::AUTOMATIC_JOIN === $grant->source) {
      $policy = $this->joins->policy($organizationId);
      if ($policy->organizationId === $organizationId && OrganizationJoinMode::AUTOMATIC === $policy->mode
        && $policy->roleId === $grant->reference && [$grant->reference] === $roleIds) {
        $this->access->assertEligibleRole($organizationId, $grant->reference);

        return;
      }

      throw new OrganizationJoinException('organization_join_unavailable');
    }

    throw new OrganizationAccessDeniedException('The membership grant is no longer authorized.');
  }
  // #endregion
}
