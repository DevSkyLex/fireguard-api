<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\ResendOrganizationInvitation;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\NotificationChannel;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Service\{InvitationInvalidationTrait, OrganizationInvitationNotifier};
use Organization\Domain\Event\Invitation\OrganizationInvitationSentEvent;
use Organization\Domain\Exception\{OrganizationInvitationNotFoundException, OrganizationInvitationNotificationFailedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, TransactionManagerPort};
use Throwable;
use User\Application\Port\Outbound\UserRepositoryPort;

/**
 * UseCase ResendOrganizationInvitationHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResendOrganizationInvitationHandler implements CommandHandler
{
  use InvitationInvalidationTrait;

  private const int DEFAULT_EXPIRATION_DAYS = 7;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ResendOrganizationInvitationHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationRepositoryPort $invitationRepository the organization invitation repository port
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param UserRepositoryPort $userRepository the user repository port
   * @param OrganizationInvitationNotifier $invitationNotifier the invitation token/link/notification helper
   * @param LoggerPort $logger the logger port
   * @param TransactionManagerPort $transactionManager the transaction manager
   * @param EventDispatcherPort $eventDispatcher the event dispatcher
   */
  public function __construct(
    private OrganizationInvitationRepositoryPort $invitationRepository,
    private OrganizationRepositoryPort $organizationRepository,
    private UserRepositoryPort $userRepository,
    private OrganizationInvitationNotifier $invitationNotifier,
    private LoggerPort $logger,
    private TransactionManagerPort $transactionManager,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Regenerates the invitation token, resets the expiry and re-sends the
   * invitation email, returning a fresh accept link.
   *
   * @since 1.0.0
   *
   * @param ResendOrganizationInvitationCommand $command the command payload
   *
   * @return ResendOrganizationInvitationResult the use case result
   */
  public function __invoke(ResendOrganizationInvitationCommand $command): ResendOrganizationInvitationResult
  {
    $invitation = $this->invitationRepository->findById(OrganizationInvitationId::fromString($command->invitationId));

    if (null === $invitation) {
      throw OrganizationInvitationNotFoundException::withId($command->invitationId);
    }

    if ((string) $invitation->organizationId() !== (string) OrganizationId::fromString($command->organizationId)) {
      throw OrganizationInvitationNotFoundException::withId($command->invitationId);
    }

    $organization = $this->organizationRepository->findById($invitation->organizationId());
    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $token = $this->invitationNotifier->generateToken();
    $tokenHash = $this->invitationNotifier->hashToken($token);
    $acceptUrl = $this->invitationNotifier->buildAcceptUrl($token);
    $now = new DateTimeImmutable();
    $expiresAt = $now->modify('+' . self::DEFAULT_EXPIRATION_DAYS . ' days');

    /** @var ResendOrganizationInvitationResult $result */
    $result = $this->transactionManager->transactional(function () use (
      $invitation,
      $tokenHash,
      $expiresAt,
      $now,
      $acceptUrl,
    ): ResendOrganizationInvitationResult {
      $invitation->renew($tokenHash, $expiresAt, $now);
      $this->invitationRepository->save($invitation);

      return new ResendOrganizationInvitationResult(
        invitationId: (string) $invitation->id(),
        organizationId: (string) $invitation->organizationId(),
        email: (string) $invitation->email(),
        status: $invitation->status()->value,
        invitedByUserId: $invitation->invitedByUserId(),
        expiresAt: $invitation->expiresAt(),
        createdAt: $invitation->createdAt(),
        updatedAt: $invitation->updatedAt(),
        roleIds: $this->invitationRepository->findRoleIdsForInvitation($invitation->id()),
        acceptUrl: $acceptUrl,
      );
    });

    $this->eventDispatcher->dispatch(new OrganizationInvitationSentEvent(
      organizationId: $command->organizationId,
      invitationId: $command->invitationId,
      invitedEmail: (string) $invitation->email(),
      invitedByUserId: $command->resentByUserId,
      resend: true,
    ));

    $existingUser = $this->userRepository->findByEmail($invitation->email());
    $recipientUserId = null !== $existingUser ? (string) $existingUser->id() : null;
    $emailLocale = $this->invitationNotifier->clampLocale($existingUser?->locale()->value);

    $notification = null;

    try {
      $notification = $this->invitationNotifier->send(
        organizationName: (string) $organization->name(),
        email: (string) $invitation->email(),
        acceptUrl: $acceptUrl,
        expiresAt: $expiresAt,
        recipientUserId: $recipientUserId,
        locale: $emailLocale,
        organizationId: (string) $invitation->organizationId(),
      );
    } catch (Throwable $exception) {
      $this->logger->warning('Invitation resend notification dispatch failed.', [
        'organizationId' => (string) $invitation->organizationId(),
        'invitationId' => (string) $invitation->id(),
        'recipientEmail' => (string) $invitation->email(),
        'error' => $exception->getMessage(),
      ]);
    }

    if (!($notification?->isDelivered(NotificationChannel::EMAIL) ?? false)) {
      $this->invalidateInvitation(
        invitationId: $invitation->id(),
        revokedByUserId: $command->resentByUserId,
      );

      $this->logger->warning('Resent invitation was revoked because its notification email could not be delivered.', [
        'organizationId' => (string) $invitation->organizationId(),
        'invitationId' => (string) $invitation->id(),
        'recipientEmail' => (string) $invitation->email(),
      ]);

      throw OrganizationInvitationNotificationFailedException::withId((string) $invitation->id());
    }

    return $result;
  }
  // #endregion
}
