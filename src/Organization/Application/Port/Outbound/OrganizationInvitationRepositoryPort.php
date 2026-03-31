<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationRoleId};
use Shared\Domain\ValueObject\Email;

/**
 * Port OrganizationInvitationRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationInvitationRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists an organization invitation aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitation $invitation the invitation aggregate
   */
  public function save(OrganizationInvitation $invitation): void;

  /**
   * Method findById.
   *
   * Finds an invitation by identifier.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationId $id the invitation identifier
   *
   * @return ?OrganizationInvitation the invitation aggregate when found
   */
  public function findById(OrganizationInvitationId $id): ?OrganizationInvitation;

  /**
   * Method findByTokenHash.
   *
   * Finds an invitation by hashed token.
   *
   * @since 1.0.0
   *
   * @param string $tokenHash the hashed invitation token
   *
   * @return ?OrganizationInvitation the invitation aggregate when found
   */
  public function findByTokenHash(string $tokenHash): ?OrganizationInvitation;

  /**
   * Method findPendingByOrganizationAndEmail.
   *
   * Finds a pending invitation by organization and email.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param Email $email the invited email
   *
   * @return ?OrganizationInvitation the pending invitation when found
   */
  public function findPendingByOrganizationAndEmail(OrganizationId $organizationId, Email $email): ?OrganizationInvitation;

  /**
   * Method findByOrganizationId.
   *
   * Lists invitations for an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return list<OrganizationInvitation> the organization invitations
   */
  public function findByOrganizationId(OrganizationId $organizationId): array;

  /**
   * Counts all invitations for an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return int the invitation count
   */
  public function countByOrganizationId(OrganizationId $organizationId): int;

  /**
   * Method countPendingByOrganizationId.
   *
   * Counts pending invitations for an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return int the pending invitation count
   */
  public function countPendingByOrganizationId(OrganizationId $organizationId): int;

  /**
   * Counts invitations grouped by status for an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return array<string, int> map of status => count
   */
  public function countByStatusForOrganizationId(OrganizationId $organizationId): array;

  /**
   * Method replaceRoleIds.
   *
   * Replaces role assignments for an invitation.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationId $invitationId the invitation identifier
   * @param list<OrganizationRoleId> $roleIds the role identifiers
   */
  public function replaceRoleIds(OrganizationInvitationId $invitationId, array $roleIds): void;

  /**
   * Method findRoleIdsForInvitation.
   *
   * Returns role identifiers assigned to an invitation.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationId $invitationId the invitation identifier
   *
   * @return list<string> the assigned role identifiers
   */
  public function findRoleIdsForInvitation(OrganizationInvitationId $invitationId): array;
  // #endregion
}
