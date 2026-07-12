<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\OrganizationInvitationId;

/**
 * Trait InvitationInvalidationTrait.
 *
 * Shared "revoke a still-pending invitation in its own transaction" step used
 * by the invite and resend use cases when a freshly issued token cannot be
 * delivered, so the invitee's dead old link is never mistaken for a live one.
 * The using handler must expose an `$invitationRepository`
 * (OrganizationInvitationRepositoryPort) and a `$transactionManager`
 * (TransactionManagerPort).
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait InvitationInvalidationTrait
{
  /**
   * Method invalidateInvitation.
   *
   * Revokes the invitation when it is still pending, inside its own
   * transaction, returning it — or null when it was already resolved or gone.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationId $invitationId the invitation identifier
   * @param string $revokedByUserId the revoker user identifier
   *
   * @return OrganizationInvitation|null the invalidated invitation, or null
   */
  private function invalidateInvitation(
    OrganizationInvitationId $invitationId,
    string $revokedByUserId,
  ): ?OrganizationInvitation {
    /** @var OrganizationInvitation|null $invalidated */
    $invalidated = $this->transactionManager->transactional(function () use (
      $invitationId,
      $revokedByUserId,
    ): ?OrganizationInvitation {
      $invitation = $this->invitationRepository->findById($invitationId);
      if (null === $invitation || !$invitation->status()->isPending()) {
        return null;
      }

      $invitation->revoke($revokedByUserId);
      $this->invitationRepository->save($invitation);

      return $invitation;
    });

    return $invalidated;
  }
}
