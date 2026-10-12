<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use DateTimeInterface;
use Otp\Application\Contract\Event\Totp\{RecoveryCodeConsumedEvent, RecoveryCodesGeneratedEvent, TotpEnrollmentConfirmedEvent, TotpEnrollmentDisabledEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Otp-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
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
      'otp.totp_enrollment_confirmed_event' => 'onTotpEnrollmentConfirmed',
      'otp.totp_enrollment_disabled_event' => 'onTotpEnrollmentDisabled',
      'otp.recovery_codes_generated_event' => 'onRecoveryCodesGenerated',
      'otp.recovery_code_consumed_event' => 'onRecoveryCodeConsumed',
    ];
  }

  /**
   * Method onTotpEnrollmentConfirmed.
   *
   * Records a TOTP enrollment activation.
   *
   * @since 1.0.0
   *
   * @param TotpEnrollmentConfirmedEvent $event the domain event
   */
  public function onTotpEnrollmentConfirmed(TotpEnrollmentConfirmedEvent $event): void
  {

    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'otp.totp_enrolled',
      actorType: 'user',
      actorId: $event->userId,
      metadata: [],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onTotpEnrollmentDisabled.
   *
   * Records a TOTP enrollment being disabled.
   *
   * @since 1.0.0
   *
   * @param TotpEnrollmentDisabledEvent $event the domain event
   */
  public function onTotpEnrollmentDisabled(TotpEnrollmentDisabledEvent $event): void
  {

    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'otp.totp_disabled',
      actorType: 'user',
      actorId: $event->userId,
      metadata: [],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onRecoveryCodesGenerated
   *
   * @access public
   *
   * @param RecoveryCodesGeneratedEvent $event committed recovery lot fact
   *
   * @return void
   */
  public function onRecoveryCodesGenerated(RecoveryCodesGeneratedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'otp.recovery_codes_generated',
      actorType: 'user',
      actorId: $event->userId,
      metadata: ['count' => $event->count, 'regenerated' => $event->regenerated, 'generatedAt' => $event->generatedAt->format(DateTimeInterface::ATOM)],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onRecoveryCodeConsumed
   *
   * @access public
   *
   * @param RecoveryCodeConsumedEvent $event committed recovery use fact
   *
   * @return void
   */
  public function onRecoveryCodeConsumed(RecoveryCodeConsumedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'otp.recovery_code_consumed',
      actorType: 'user',
      actorId: $event->userId,
      metadata: ['purpose' => $event->purpose],
      occurredAt: $event->occurredAt,
    ));
  }
  // #endregion
}
