<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\DeliverOrganizationInvitation;

use Notification\Application\Contract\Notification\NotificationChannel;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Service\OrganizationInvitationNotifier;
use Organization\Domain\ValueObject\OrganizationInvitationId;
use RuntimeException;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\IdempotentConsumerPort;
use User\Application\Port\Outbound\UserRepositoryPort;

use function hash_equals;

/** Runs only after the invitation and its import receipt have committed. */
final readonly class DeliverOrganizationInvitationHandler implements CommandHandler
{
  public function __construct(
    private OrganizationInvitationRepositoryPort $invitations,
    private OrganizationRepositoryPort $organizations,
    private UserRepositoryPort $users,
    private OrganizationInvitationNotifier $notifier,
    private IdempotentConsumerPort $consumer,
  ) {
  }

  public function __invoke(DeliverOrganizationInvitationCommand $command): VoidResult
  {
    $this->consumer->consume($command->invitationId, 'invitation.delivery:' . $command->tokenHash, function () use ($command): void {
      $invitation = $this->invitations->findById(OrganizationInvitationId::fromString($command->invitationId));
      if (null === $invitation || !$invitation->status()->isPending() || $invitation->isExpired()
        || !hash_equals($invitation->tokenHash(), $command->tokenHash)) {
        return;
      }
      $organization = $this->organizations->findById($invitation->organizationId());
      if (null === $organization) {
        return;
      }
      $user = $this->users->findByEmail($invitation->email());
      $notification = $this->notifier->send(
        organizationName: (string) $organization->name(),
        email: (string) $invitation->email(),
        acceptUrl: $command->acceptUrl,
        expiresAt: $invitation->expiresAt(),
        recipientUserId: null !== $user ? (string) $user->id() : null,
        locale: $this->notifier->clampLocale($user?->locale()->value),
        organizationId: (string) $invitation->organizationId(),
      );
      if (!$notification->isDelivered(NotificationChannel::EMAIL)) {
        // No consumption receipt: Messenger retries and ultimately retains the
        // failed delivery for an explicit operator retry. Never log its URL.
        throw new RuntimeException('The invitation email could not be delivered.');
      }
    });

    return new VoidResult();
  }
}
