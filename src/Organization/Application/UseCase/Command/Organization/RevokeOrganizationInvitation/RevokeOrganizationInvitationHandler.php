<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RevokeOrganizationInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Outbound\OrganizationInvitationRepositoryPort;
use Organization\Domain\Exception\OrganizationInvitationNotFoundException;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * UseCase RevokeOrganizationInvitationHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeOrganizationInvitationHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RevokeOrganizationInvitationHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationRepositoryPort $invitationRepository the organization invitation repository port
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private OrganizationInvitationRepositoryPort $invitationRepository,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Revokes a pending invitation.
   *
   * @since 1.0.0
   *
   * @param RevokeOrganizationInvitationCommand $command the command payload
   *
   * @return RevokeOrganizationInvitationResult the use case result
   */
  public function __invoke(RevokeOrganizationInvitationCommand $command): RevokeOrganizationInvitationResult
  {
    $invitation = $this->invitationRepository->findById(OrganizationInvitationId::fromString($command->invitationId));

    if (null === $invitation) {
      throw OrganizationInvitationNotFoundException::withId($command->invitationId);
    }

    if ((string) $invitation->organizationId() !== (string) OrganizationId::fromString($command->organizationId)) {
      throw OrganizationInvitationNotFoundException::withId($command->invitationId);
    }

    $now = new DateTimeImmutable();
    if ($invitation->isExpired($now) && $invitation->status()->isPending()) {
      $invitation->expire($now);
      $this->invitationRepository->save($invitation);

      throw new InvalidArgumentException('Invitation has already expired.');
    }

    if (!$invitation->status()->isPending()) {
      throw new InvalidArgumentException('Only pending invitations can be revoked.');
    }

    /** @var RevokeOrganizationInvitationResult $result */
    $result = $this->transactionManager->transactional(function () use (
      $invitation,
      $command,
      $now,
    ): RevokeOrganizationInvitationResult {
      $invitation->revoke($command->revokedByUserId, $now);
      $this->invitationRepository->save($invitation);

      return new RevokeOrganizationInvitationResult(
        invitationId: (string) $invitation->id(),
        organizationId: (string) $invitation->organizationId(),
        email: (string) $invitation->email(),
        status: $invitation->status()->value,
        invitedByUserId: $invitation->invitedByUserId(),
        revokedByUserId: $invitation->revokedByUserId(),
        expiresAt: $invitation->expiresAt(),
        createdAt: $invitation->createdAt(),
        updatedAt: $invitation->updatedAt(),
        acceptedAt: $invitation->acceptedAt(),
        revokedAt: $invitation->revokedAt(),
        roleIds: $this->invitationRepository->findRoleIdsForInvitation($invitation->id()),
      );
    });

    return $result;
  }
  // #endregion
}
