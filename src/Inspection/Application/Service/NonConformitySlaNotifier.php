<?php

declare(strict_types=1);

namespace Inspection\Application\Service;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Inbound\OrganizationNotificationPolicyPort;
use Throwable;

use function sprintf;

/**
 * Service NonConformitySlaNotifier.
 *
 * Sends a non-conformity SLA breach escalation to an organization's
 * administrators, honoring the `nonConformitySlaBreached` category toggle
 * and the `inAppEnabled`/`emailEnabled` channel toggles — mirrors
 * `Maintenance\Application\Service\MaintenanceReminderNotifier`. Best-effort:
 * a notification failure must never fail the recurring sweep.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformitySlaNotifier
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NotificationPort $notifications the notification inbound port
   * @param OrganizationNotificationPolicyPort $policy the organization notification policy port
   * @param NonConformitySlaRecipientResolver $recipients the escalation recipient resolver
   */
  public function __construct(
    private NotificationPort $notifications,
    private OrganizationNotificationPolicyPort $policy,
    private NonConformitySlaRecipientResolver $recipients,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method escalate.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $inspectionId the owning inspection identifier
   * @param string $nonConformityId the non-conformity identifier
   * @param string $severity the non-conformity severity value
   * @param int $slaDays the SLA that was breached, in days
   * @param DateTimeImmutable $openedAt the instant the non-conformity was opened
   */
  public function escalate(
    string $organizationId,
    string $inspectionId,
    string $nonConformityId,
    string $severity,
    int $slaDays,
    DateTimeImmutable $openedAt,
  ): void {
    try {
      $policy = $this->policy->notificationPolicy($organizationId);
      if (!$policy->nonConformitySlaBreached) {
        return;
      }

      $channels = [];
      if ($policy->inAppEnabled) {
        $channels[] = NotificationChannel::MERCURE;
      }
      if ($policy->emailEnabled) {
        $channels[] = NotificationChannel::EMAIL;
      }
      if ([] === $channels) {
        return;
      }

      $subject = 'Non-conformity resolution SLA breached';
      $body = sprintf(
        'A %s non-conformity opened on %s has exceeded its %d-day resolution SLA.',
        $severity,
        $openedAt->format('Y-m-d'),
        $slaDays,
      );
      $payload = [
        'nonConformityId' => $nonConformityId,
        'inspectionId' => $inspectionId,
        'organizationId' => $organizationId,
        'severity' => $severity,
        'slaDays' => $slaDays,
        'openedAt' => $openedAt->format('c'),
      ];

      foreach ($this->recipients->organizationAdministrators($organizationId) as $userId) {
        try {
          $this->notifications->send(new SendNotificationRequest(
            type: 'non_conformity.sla_breached',
            subject: $subject,
            body: $body,
            channels: $channels,
            payload: $payload,
            recipientUserId: $userId,
            organizationId: $organizationId,
          ));
        } catch (Throwable) {
          // Best-effort per recipient: one failed delivery must not skip the rest.
        }
      }
    } catch (Throwable) {
      // Notifications must never fail the recurring sweep.
    }
  }
  // #endregion
}
