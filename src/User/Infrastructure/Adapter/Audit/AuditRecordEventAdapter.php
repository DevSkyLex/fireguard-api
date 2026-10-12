<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use DateTimeImmutable;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use User\Application\Contract\Event\{UserEmailChangeCancelledEvent, UserEmailChangeConfirmedEvent, UserEmailChangeRequestedEvent};

/**
 * Class AuditRecordEventAdapter
 *
 * Projects User-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
 *
 * @category Adapter
 */
final readonly class AuditRecordEventAdapter implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the published durable recording seam.
   *
   * @access public
   *
   * @param AuditRecordingPort $audit generic Audit write capability
   *
   * @return void
   */
  public function __construct(private AuditRecordingPort $audit)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * Preserves the existing owner event names and audit action mapping.
   *
   * @access public
   *
   * @return array<string, string> owner event listeners
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'user.user_email_change_requested_event' => 'onUserEmailChangeRequested',
      'user.user_email_change_confirmed_event' => 'onUserEmailChangeConfirmed',
      'user.user_email_change_cancelled_event' => 'onUserEmailChangeCancelled',
    ];
  }

  /**
   * Method onUserEmailChangeRequested.
   *
   * Records a sign-in email change request. Both addresses are PII:
   * they go through the sanitizer like every other email in the
   * ledger — sanitized display form plus deterministic hash, never
   * the raw value in the metadata.
   *
   * @since 1.0.0
   *
   * @param UserEmailChangeRequestedEvent $event the domain event
   */
  public function onUserEmailChangeRequested(UserEmailChangeRequestedEvent $event): void
  {
    $this->recordEmailChangeAudit('user.email_change_requested', $event->userId, $event->currentEmail, $event->newEmail, $event->occurredAt);
  }

  /**
   * Method onUserEmailChangeConfirmed.
   *
   * Records an applied sign-in email change (sessions revoked).
   *
   * @since 1.0.0
   *
   * @param UserEmailChangeConfirmedEvent $event the domain event
   */
  public function onUserEmailChangeConfirmed(UserEmailChangeConfirmedEvent $event): void
  {
    $this->recordEmailChangeAudit('user.email_change_confirmed', $event->userId, $event->currentEmail, $event->newEmail, $event->occurredAt);
  }

  /**
   * Method onUserEmailChangeCancelled.
   *
   * Records a cancelled sign-in email change request.
   *
   * @since 1.0.0
   *
   * @param UserEmailChangeCancelledEvent $event the domain event
   */
  public function onUserEmailChangeCancelled(UserEmailChangeCancelledEvent $event): void
  {
    $this->recordEmailChangeAudit('user.email_change_cancelled', $event->userId, $event->currentEmail, $event->newEmail, $event->occurredAt);
  }

  /**
   * Method recordEmailChangeAudit.
   *
   * Shared shape of the three email-change ledger entries.
   *
   * @since 1.0.0
   *
   * @param string $action the audit action
   * @param string $userId the acting user
   * @param string $currentEmail the current (old) address
   * @param string $newEmail the requested/applied new address
   * @param DateTimeImmutable $occurredAt when the event occurred
   */
  private function recordEmailChangeAudit(
    string $action,
    string $userId,
    string $currentEmail,
    string $newEmail,
    DateTimeImmutable $occurredAt,
  ): void {

    $this->audit->requestRecord(new AuditRecordRequest(
      action: $action,
      actorType: 'user',
      actorId: $userId,
      actorEmail: $currentEmail,
      subjectType: 'user',
      subjectId: $userId,
      emailMetadata: [
        'current_email' => $currentEmail,
        'new_email' => $newEmail,
      ],
      occurredAt: $occurredAt,
    ));
  }
  // #endregion
}
