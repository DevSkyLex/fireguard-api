<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RemoveOrganizationMember;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\OrganizationLastAdminGuardPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Event\Member\OrganizationMemberRemovedEvent;
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, TransactionManagerPort};
use Throwable;

use function sprintf;

/**
 * UseCase RemoveOrganizationMemberHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveOrganizationMemberHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RemoveOrganizationMemberHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher port
   * @param OrganizationLastAdminGuardPort $lastAdminGuard the last-administrator lockout guard port
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private NotificationPort $notificationPort,
    private LoggerPort $logger,
    private EventDispatcherPort $eventDispatcher,
    private OrganizationLastAdminGuardPort $lastAdminGuard,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Deactivates an organization member (soft-remove).
   *
   * @since 1.0.0
   *
   * @param RemoveOrganizationMemberCommand $command the command payload
   *
   * @return RemoveOrganizationMemberResult the use case result
   */
  public function __invoke(RemoveOrganizationMemberCommand $command): RemoveOrganizationMemberResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $memberId = OrganizationMemberId::fromString($command->memberId);

    // The last-administrator check and the deactivation it authorizes run in one
    // transaction, under the advisory lock the guard takes: two concurrent
    // removals would otherwise both read "another administrator remains" and both
    // deactivate one, leaving the organization with nobody able to re-admit
    // members. The member is loaded inside the lock so the loser of the race
    // reads the state the winner committed.
    /** @var ?OrganizationMember $member */
    $member = $this->transactionManager->transactional(
      function () use ($command, $organizationId, $memberId): ?OrganizationMember {
        $this->lastAdminGuard->assertCanRemoveMember($command->organizationId, $command->memberId);

        $member = $this->memberRepository->findById($memberId);

        if (null === $member || (string) $member->organizationId() !== (string) $organizationId) {
          throw OrganizationMemberNotFoundException::withId($command->memberId);
        }

        if (!$member->isActive()) {
          // Already removed — idempotent no-op, and nothing to announce.
          return null;
        }

        $member->deactivate();
        $this->memberRepository->save($member);

        return $member;
      },
    );

    if (null === $member) {
      return new RemoveOrganizationMemberResult(
        memberId: (string) $memberId,
        organizationId: (string) $organizationId,
      );
    }

    $this->eventDispatcher->dispatch(new OrganizationMemberRemovedEvent(
      organizationId: (string) $organizationId,
      memberId: (string) $memberId,
      userId: $member->userId(),
    ));

    $removedAt = new DateTimeImmutable();

    try {
      $this->notificationPort->send(new SendNotificationRequest(
        type: NotificationType::ORGANIZATION_MEMBER_REMOVED,
        subject: 'Organization access removed',
        body: sprintf('Your access to %s has been removed.', (string) $organization->name()),
        channels: [NotificationChannel::MERCURE],
        payload: [
          'organizationId' => (string) $organizationId,
          'memberId' => (string) $memberId,
          'organizationName' => (string) $organization->name(),
          'removedAt' => $removedAt->format('c'),
        ],
        recipientUserId: $member->userId(),
        organizationId: (string) $organizationId,
      ));
    } catch (Throwable $exception) {
      $this->logger->warning('Organization member removed notification dispatch failed.', [
        'organizationId' => (string) $organizationId,
        'memberId' => (string) $memberId,
        'recipientUserId' => $member->userId(),
        'error' => $exception->getMessage(),
      ]);
    }

    return new RemoveOrganizationMemberResult(
      memberId: (string) $memberId,
      organizationId: (string) $organizationId,
    );
  }
  // #endregion
}
