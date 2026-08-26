<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\AcceptOrganizationInvitation;

use DateTimeImmutable;
use InvalidArgumentException;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Service\OrganizationInvitationTokenHasher;
use Organization\Application\UseCase\Command\Organization\AddOrganizationMember\{AddOrganizationMemberCommand, AddOrganizationMemberHandler};
use Organization\Domain\Event\Invitation\OrganizationInvitationAcceptedEvent;
use Organization\Domain\Event\Member\OrganizationMemberAddedEvent;
use Organization\Domain\Exception\{OrganizationInvitationNotFoundException, OrganizationInvitationNotPendingException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

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
   * @param OrganizationQuotaPort $quota the organization quota port
   * @param TransactionManagerPort $transactionManager the transaction manager
   * @param OrganizationInvitationTokenHasher $tokenHasher the shared token generator/hasher
   * @param EventDispatcherPort $eventDispatcher the event dispatcher
   */
  public function __construct(
    private OrganizationInvitationRepositoryPort $invitationRepository,
    private OrganizationRepositoryPort $organizationRepository,
    private AddOrganizationMemberHandler $addOrganizationMemberHandler,
    private OrganizationQuotaPort $quota,
    private NotificationPort $notificationPort,
    private LoggerPort $logger,
    private TransactionManagerPort $transactionManager,
    private OrganizationInvitationTokenHasher $tokenHasher,
    private EventDispatcherPort $eventDispatcher,
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
      throw InvalidValueException::because('Invitation token is required.');
    }

    $invitation = $this->invitationRepository->findByTokenHash(
      tokenHash: $this->tokenHasher->hash($token),
    );

    if (null === $invitation) {
      throw OrganizationInvitationNotFoundException::withToken();
    }

    $now = new DateTimeImmutable();

    if ($invitation->isExpired($now) && $invitation->status()->isPending()) {
      $invitation->expire($now);
      $this->invitationRepository->save($invitation);

      throw OrganizationInvitationNotPendingException::expired();
    }

    if (!$invitation->status()->isPending()) {
      throw OrganizationInvitationNotPendingException::noLongerPending();
    }

    $invitedEmail = strtolower(trim((string) $invitation->email()));
    $authenticatedEmail = strtolower(trim($command->userEmail));
    if ($invitedEmail !== $authenticatedEmail) {
      throw new InvalidArgumentException('Invitation email does not match the authenticated user.');
    }

    $memberWasAdded = false;

    /** @var AcceptOrganizationInvitationResult $result */
    $result = $this->transactionManager->transactional(function () use (
      $invitation,
      $command,
      $now,
      &$memberWasAdded,
    ): AcceptOrganizationInvitationResult {
      // Enforce the plan's member cap on the acceptance path too: the direct-add
      // and invite processors gate on active members + pending invitations, but
      // an invitee reaching this handler would otherwise bypass the quota (e.g.
      // after a plan downgrade left the organization already at its cap).
      $this->quota->assertCanAcceptMember((string) $invitation->organizationId());

      $roleIds = $this->invitationRepository->findRoleIdsForInvitation($invitation->id());

      $memberResult = $this->addOrganizationMemberHandler->__invoke(new AddOrganizationMemberCommand(
        organizationId: (string) $invitation->organizationId(),
        userId: $command->userId,
        roleIds: $roleIds,
        sendMemberNotification: false,
        // The acceptance path already took the members lock and enforced the cap
        // via assertCanAcceptMember (active-only count); re-running assertCanAdd
        // here would double-count the pending invitation being accepted.
        enforceQuota: false,
        // The nested handler runs inside THIS transaction: it must not audit
        // yet, or a rollback of the outer accept would leave a phantom
        // member_added ledger row. This handler dispatches post-commit below.
        emitMemberAddedEvent: false,
      ));
      $memberWasAdded = $memberResult->wasCreatedOrReactivated;

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

    if ($memberWasAdded) {
      $this->eventDispatcher->dispatch(new OrganizationMemberAddedEvent(
        organizationId: (string) $invitation->organizationId(),
        memberId: $result->memberId,
        userId: $command->userId,
        roleIds: $result->roleIds,
      ));
    }

    $this->eventDispatcher->dispatch(new OrganizationInvitationAcceptedEvent(
      organizationId: (string) $invitation->organizationId(),
      invitationId: (string) $invitation->id(),
      memberId: $result->memberId,
      userId: $command->userId,
      userEmail: $command->userEmail,
      roleIds: $result->roleIds,
    ));

    $this->dispatchMercure(
      type: NotificationType::ORGANIZATION_INVITATION_ACCEPTED,
      subject: 'Invitation accepted',
      body: sprintf('%s accepted your organization invitation.', $authenticatedEmail),
      payload: [
        'organizationId' => (string) $invitation->organizationId(),
        'invitationId' => (string) $invitation->id(),
        'acceptedUserId' => $command->userId,
        'acceptedEmail' => $authenticatedEmail,
        'acceptedAt' => $now->format('c'),
      ],
      recipientUserId: $invitation->invitedByUserId(),
      organizationId: (string) $invitation->organizationId(),
      failureMessage: 'Invitation accepted notification dispatch failed.',
      logContext: [
        'organizationId' => (string) $invitation->organizationId(),
        'invitationId' => (string) $invitation->id(),
        'recipientUserId' => $invitation->invitedByUserId(),
      ],
    );

    $organization = $this->organizationRepository->findById(new OrganizationId((string) $invitation->organizationId()));
    $ownerUserId = $organization?->ownerUserId();

    if (null !== $organization && null !== $ownerUserId && $ownerUserId !== $command->userId && $ownerUserId !== $invitation->invitedByUserId()) {
      $this->dispatchMercure(
        type: NotificationType::ORGANIZATION_MEMBER_JOINED,
        subject: 'New member joined your organization',
        body: sprintf('%s joined %s.', $authenticatedEmail, (string) $organization->name()),
        payload: [
          'organizationId' => (string) $invitation->organizationId(),
          'invitationId' => (string) $invitation->id(),
          'memberId' => $result->memberId,
          'joinedUserId' => $command->userId,
          'joinedEmail' => $authenticatedEmail,
          'joinedAt' => $result->joinedAt->format('c'),
        ],
        recipientUserId: $ownerUserId,
        organizationId: (string) $invitation->organizationId(),
        failureMessage: 'Member joined notification dispatch failed.',
        logContext: [
          'organizationId' => (string) $invitation->organizationId(),
          'invitationId' => (string) $invitation->id(),
          'recipientUserId' => $ownerUserId,
        ],
      );
    }

    return $result;
  }

  /**
   * Method dispatchMercure.
   *
   * Sends a fire-and-forget Mercure notification, swallowing and logging any
   * delivery failure so a real-time push never aborts the accept flow. Shared
   * by the invitation-accepted and member-joined pushes.
   *
   * @since 1.0.0
   *
   * @param string $type the notification type
   * @param string $subject the notification subject
   * @param string $body the notification body
   * @param array<string, mixed> $payload the Mercure payload
   * @param string $recipientUserId the recipient user identifier
   * @param string $organizationId the organization the invitation/membership belongs to
   * @param string $failureMessage the log message written on dispatch failure
   * @param array<string, mixed> $logContext extra context added to the failure log
   */
  private function dispatchMercure(
    string $type,
    string $subject,
    string $body,
    array $payload,
    string $recipientUserId,
    string $organizationId,
    string $failureMessage,
    array $logContext,
  ): void {
    try {
      $this->notificationPort->send(new SendNotificationRequest(
        type: $type,
        subject: $subject,
        body: $body,
        channels: [NotificationChannel::MERCURE],
        payload: $payload,
        recipientUserId: $recipientUserId,
        organizationId: $organizationId,
      ));
    } catch (Throwable $exception) {
      $this->logger->warning($failureMessage, [...$logContext, 'error' => $exception->getMessage()]);
    }
  }
  // #endregion
}
