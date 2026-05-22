<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\AcceptOrganizationInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Command\Organization\AddOrganizationMember\{AddOrganizationMemberCommand, AddOrganizationMemberHandler};
use Organization\Domain\Exception\OrganizationInvitationNotFoundException;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{LoggerPort, TransactionManagerPort};
use Throwable;

use function hash;
use function sprintf;
use function strtolower;
use function trim;

/**
 * UseCase AcceptOrganizationInvitationHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AcceptOrganizationInvitationHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the AcceptOrganizationInvitationHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationRepositoryPort $invitationRepository the organization invitation repository port
   * @param AddOrganizationMemberHandler $addOrganizationMemberHandler the member add use case handler
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private OrganizationInvitationRepositoryPort $invitationRepository,
    private OrganizationRepositoryPort $organizationRepository,
    private AddOrganizationMemberHandler $addOrganizationMemberHandler,
    private NotificationPort $notificationPort,
    private LoggerPort $logger,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Accepts an invitation and provisions the organization membership.
   *
   * @since 1.0.0
   *
   * @param AcceptOrganizationInvitationCommand $command the command payload
   *
   * @return AcceptOrganizationInvitationResult the use case result
   */
  public function __invoke(AcceptOrganizationInvitationCommand $command): AcceptOrganizationInvitationResult
  {
    $token = trim($command->token);
    if ('' === $token) {
      throw new InvalidArgumentException('Invitation token is required.');
    }

    $invitation = $this->invitationRepository->findByTokenHash(
      tokenHash: $this->hashInvitationToken($token),
    );

    if (null === $invitation) {
      throw OrganizationInvitationNotFoundException::withToken();
    }

    $now = new DateTimeImmutable();

    if ($invitation->isExpired($now) && $invitation->status()->isPending()) {
      $invitation->expire($now);
      $this->invitationRepository->save($invitation);

      throw new InvalidArgumentException('Invitation has expired.');
    }

    if (!$invitation->status()->isPending()) {
      throw new InvalidArgumentException('Invitation is no longer pending.');
    }

    $invitedEmail = strtolower(trim((string) $invitation->email()));
    $authenticatedEmail = strtolower(trim($command->userEmail));
    if ($invitedEmail !== $authenticatedEmail) {
      throw new InvalidArgumentException('Invitation email does not match the authenticated user.');
    }

    /** @var AcceptOrganizationInvitationResult $result */
    $result = $this->transactionManager->transactional(function () use (
      $invitation,
      $command,
      $now,
    ): AcceptOrganizationInvitationResult {
      $roleIds = $this->invitationRepository->findRoleIdsForInvitation($invitation->id());

      $memberResult = $this->addOrganizationMemberHandler->__invoke(new AddOrganizationMemberCommand(
        organizationId: (string) $invitation->organizationId(),
        userId: $command->userId,
        roleIds: $roleIds,
        sendMemberNotification: false,
      ));

      $invitation->accept($command->userId, $now);
      $this->invitationRepository->save($invitation);

      return new AcceptOrganizationInvitationResult(
        invitationId: (string) $invitation->id(),
        memberId: $memberResult->memberId,
        organizationId: $memberResult->organizationId,
        userId: $memberResult->userId,
        roleIds: $memberResult->roleIds,
        isActive: $memberResult->isActive,
        joinedAt: $memberResult->joinedAt,
      );
    });

    try {
      $this->notificationPort->send(new SendNotificationRequest(
        type: NotificationType::ORGANIZATION_INVITATION_ACCEPTED,
        subject: 'Invitation accepted',
        body: sprintf('%s accepted your organization invitation.', $authenticatedEmail),
        channels: [NotificationChannel::MERCURE],
        payload: [
          'organizationId' => (string) $invitation->organizationId(),
          'invitationId' => (string) $invitation->id(),
          'acceptedUserId' => $command->userId,
          'acceptedEmail' => $authenticatedEmail,
          'acceptedAt' => $now->format('c'),
        ],
        recipientUserId: $invitation->invitedByUserId(),
      ));
    } catch (Throwable $exception) {
      $this->logger->warning('Invitation accepted notification dispatch failed.', [
        'organizationId' => (string) $invitation->organizationId(),
        'invitationId' => (string) $invitation->id(),
        'recipientUserId' => $invitation->invitedByUserId(),
        'error' => $exception->getMessage(),
      ]);
    }

    $organization = $this->organizationRepository->findById(new OrganizationId((string) $invitation->organizationId()));
    $ownerUserId = $organization?->ownerUserId();

    if (null !== $organization && null !== $ownerUserId && $ownerUserId !== $command->userId && $ownerUserId !== $invitation->invitedByUserId()) {
      try {
        $this->notificationPort->send(new SendNotificationRequest(
          type: NotificationType::ORGANIZATION_MEMBER_JOINED,
          subject: 'New member joined your organization',
          body: sprintf('%s joined %s.', $authenticatedEmail, (string) $organization->name()),
          channels: [NotificationChannel::MERCURE],
          payload: [
            'organizationId' => (string) $invitation->organizationId(),
            'invitationId' => (string) $invitation->id(),
            'memberId' => $result->memberId,
            'joinedUserId' => $command->userId,
            'joinedEmail' => $authenticatedEmail,
            'joinedAt' => $result->joinedAt->format('c'),
          ],
          recipientUserId: $ownerUserId,
        ));
      } catch (Throwable $exception) {
        $this->logger->warning('Member joined notification dispatch failed.', [
          'organizationId' => (string) $invitation->organizationId(),
          'invitationId' => (string) $invitation->id(),
          'recipientUserId' => $ownerUserId,
          'error' => $exception->getMessage(),
        ]);
      }
    }

    return $result;
  }

  /**
   * Method hashInvitationToken.
   *
   * Computes a deterministic hash for invitation token lookup.
   *
   * @since 1.0.0
   *
   * @param string $token the raw token
   *
   * @return string the token hash
   */
  private function hashInvitationToken(string $token): string
  {
    return hash('sha256', $token);
  }
  // #endregion
}
